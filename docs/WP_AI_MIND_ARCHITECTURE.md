# WP AI Mind — Technical Architecture Spec
## Free Tier, API Key Management, Rate Limiting & Tier Design

> **Purpose:** Reference document for a new development session. Captures all design decisions, target architecture, and rationale from prior analysis. The new chat should explore the existing codebase and adapt it to this architecture — not start from scratch.

---

## Context: What Exists Today

The plugin (`wp-ai-mind`) currently:

- Uses **Freemius SDK** (product ID 26475) with a 7-day trial (`is_require_payment: true`)
- Supports 4 AI providers: **Claude, OpenAI, Gemini, Ollama** — each requires the user's own API key
- Gates features via `ProGate::is_pro()` → `wam_fs()->can_use_premium_code__premium_only()`
- Has a `UsageLogger` class that logs per-request token counts and costs to the database
- Has UI references to a "Plugin API (free tier)" concept with no backend implementation
- Uses function prefix convention: `nj_`

---

## Core Architectural Decision: The Thin Client Model

**The plugin is a UI only. All business logic lives in a backend API you control.**

This is the fundamental shift. The plugin:
- Stores an auth token
- Sends requests to your API
- Renders responses and usage data
- Has no knowledge of plan limits, models, or API keys

Your backend API:
- Validates identity
- Enforces entitlements and rate limits
- Holds all AI provider keys
- Logs all usage
- Returns structured errors with upgrade CTAs

**Why:** Any enforcement logic inside the plugin can be trivially bypassed (database edits, code modification). Every market-leading AI plugin (Jetpack AI, AI Engine, Elementor AI) has converged on this model. It also means pricing, limits, and models can change without a plugin update.

---

## What Gets Removed

| Current | Replaced by |
|---|---|
| Freemius SDK entirely | LemonSqueezy for payments + custom JWT auth |
| `ProGate::is_pro()` | API response determines what's allowed |
| `UsageLogger` (in plugin) | Usage logged server-side in Cloudflare Worker |
| Per-provider API key settings (for free users) | Removed — free users have no key to configure |
| `wam_fs()` calls throughout codebase | `NJ_Auth::get_token()` + `NJ_Entitlement::get()` |

Pro users who bring their own API key retain that capability — see Pro tier section.

---

## Identity & Authentication

### Model: Account-Based with JWT

Users create an account on your website (email + password). The plugin authenticates as that account and receives a short-lived JWT. This is the direction the market has moved — frictionless free signup, no license key friction.

**Token storage in WordPress:**

```php
// Stored in wp_options (encrypted)
wam_auth_token        // JWT, short-lived (1 hour)
wam_refresh_token     // Long-lived (30 days), used to rotate access token
wam_entitlement       // Cached plan + limits object (1-hour transient)
```

**Auth flow:**

```
1. User installs plugin
2. Plugin shows account creation screen (email + password)
   OR "Log in to existing account"
3. Plugin POSTs credentials to your API → receives JWT + refresh token
4. JWT stored in wp_options
5. All subsequent AI requests carry JWT as Bearer token
6. Plugin refreshes JWT silently when it expires (using refresh token)
```

**JWT payload:**

```json
{
  "sub": "user_12345",
  "site": "example.com",
  "plan": "trial",
  "iat": 1713300000,
  "exp": 1713303600
}
```

The JWT is signed with a secret only your API knows. The Cloudflare Worker validates the signature on every request — no database lookup required.

---

## Entitlement System

### The Entitlement Document

Your API returns a structured entitlement object. The plugin caches it as a 1-hour transient. The Cloudflare Worker re-validates it independently on every request — it does not trust the plugin's cached copy for enforcement.

```json
{
  "user_id": "u_12345",
  "plan": "trial",
  "plan_expires": "2026-04-24T00:00:00Z",
  "features": {
    "proxy_api_access": true,
    "model": "claude-haiku-4-5",
    "streaming": false,
    "max_output_tokens": 1000,
    "conversation_memory": false,
    "bulk_generation": false,
    "own_api_key": false
  },
  "usage": {
    "tokens_used_this_month": 12450,
    "tokens_remaining": 37550,
    "resets_at": "2026-05-01T00:00:00Z"
  }
}
```

### Plan Definitions

| Feature | Free | Trial (7 days) | Pro |
|---|---|---|---|
| Monthly token budget | 50,000 | 300,000 | Unlimited (own key) |
| Model | Claude Haiku | Claude Haiku | User's choice |
| Streaming | ❌ | ❌ | ✅ |
| Conversation memory | ❌ | ✅ | ✅ |
| Bulk generation | ❌ | ❌ | ✅ |
| Own API key | ❌ | ❌ | ✅ |
| Max output tokens/request | 1,000 | 2,000 | Unlimited |

**Design rationale:**
- Trial is generous enough that users experience the full value proposition before it expires
- Free plan is useful but model-limited and volume-limited — power users hit the ceiling
- The step-down from trial → free is the primary conversion moment
- Streaming and model choice are qualitative gates that create upgrade motivation independent of volume

---

## Backend API: Cloudflare Worker

### Why Cloudflare Workers

- Zero server management — no VPS, no nginx, no Docker
- Free tier: 100,000 requests/day
- Global edge deployment — low latency worldwide
- Cloudflare KV for rate limit state — built-in, no Redis needed
- Secrets (API keys) stored encrypted by Cloudflare, never in code
- Deploy via CLI in seconds; local dev with `wrangler dev`

### Project Structure

```
wp-ai-mind-proxy/
├── src/
│   └── index.js          # All Worker logic
├── wrangler.toml          # Cloudflare config
└── package.json
```

### `wrangler.toml`

```toml
name = "wp-ai-mind-proxy"
main = "src/index.js"
compatibility_date = "2024-01-01"

[[kv_namespaces]]
binding = "USAGE_KV"
id = "your_kv_namespace_id_here"

# Secrets set via CLI — never stored in this file:
# wrangler secret put ANTHROPIC_API_KEY
# wrangler secret put JWT_SECRET
# wrangler secret put LEMONSQUEEZY_WEBHOOK_SECRET
```

### `src/index.js` — Core Worker Logic

```javascript
export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Route requests
    if (url.pathname === '/v1/auth/token')      return handleAuthToken(request, env);
    if (url.pathname === '/v1/auth/refresh')    return handleAuthRefresh(request, env);
    if (url.pathname === '/v1/entitlement')     return handleEntitlement(request, env);
    if (url.pathname === '/v1/chat')            return handleChat(request, env);
    if (url.pathname === '/v1/usage')           return handleUsage(request, env);
    if (url.pathname === '/webhooks/lemonsqueezy') return handleLSWebhook(request, env);

    return json({ error: 'not_found' }, 404);
  }
};

// ─── Auth ────────────────────────────────────────────────────────────────────

async function handleAuthToken(request, env) {
  const { email, password } = await request.json();
  const user = await verifyCredentials(email, password, env);
  if (!user) return json({ error: 'invalid_credentials' }, 401);

  const accessToken  = await signJWT({ sub: user.id, plan: user.plan }, env.JWT_SECRET, 3600);
  const refreshToken = await signJWT({ sub: user.id, type: 'refresh' }, env.JWT_SECRET, 2592000);

  return json({ access_token: accessToken, refresh_token: refreshToken });
}

async function handleAuthRefresh(request, env) {
  const { refresh_token } = await request.json();
  const payload = await verifyJWT(refresh_token, env.JWT_SECRET);
  if (!payload || payload.type !== 'refresh') return json({ error: 'invalid_token' }, 401);

  const user        = await getUserById(payload.sub, env);
  const accessToken = await signJWT({ sub: user.id, plan: user.plan }, env.JWT_SECRET, 3600);

  return json({ access_token: accessToken });
}

// ─── Entitlement ─────────────────────────────────────────────────────────────

async function handleEntitlement(request, env) {
  const user = await authenticate(request, env);
  if (!user) return json({ error: 'unauthorized' }, 401);

  const used = await getMonthlyUsage(user.id, env);
  const plan = PLAN_DEFINITIONS[user.plan];

  return json({
    user_id:      user.id,
    plan:         user.plan,
    plan_expires: user.plan_expires,
    features:     plan.features,
    usage: {
      tokens_used_this_month: used,
      tokens_remaining:       Math.max(0, plan.monthly_token_budget - used),
      resets_at:              nextMonthStart(),
    }
  });
}

// ─── Chat (proxy to Anthropic) ────────────────────────────────────────────────

async function handleChat(request, env) {
  const user = await authenticate(request, env);
  if (!user) return json({ error: 'unauthorized' }, 401);

  const plan = PLAN_DEFINITIONS[user.plan];
  if (!plan.features.proxy_api_access) {
    return json({ error: 'plan_not_eligible', upgrade_url: 'https://wpaimind.com/upgrade' }, 403);
  }

  // Rate limit check
  const used = await getMonthlyUsage(user.id, env);
  if (used >= plan.monthly_token_budget) {
    return json({
      error:       'limit_exceeded',
      used,
      limit:       plan.monthly_token_budget,
      resets_at:   nextMonthStart(),
      upgrade_url: 'https://wpaimind.com/upgrade'
    }, 429);
  }

  const body = await request.json();

  // Enforce max output tokens per request
  const maxTokens = Math.min(
    body.max_tokens || plan.features.max_output_tokens,
    plan.features.max_output_tokens
  );

  const response = await fetch('https://api.anthropic.com/v1/messages', {
    method:  'POST',
    headers: {
      'x-api-key':         env.ANTHROPIC_API_KEY,
      'anthropic-version': '2023-06-01',
      'content-type':      'application/json',
    },
    body: JSON.stringify({
      model:      plan.features.model,
      max_tokens: maxTokens,
      messages:   body.messages,
    }),
  });

  const result     = await response.json();
  const tokensUsed = result.usage.input_tokens + result.usage.output_tokens;

  // Write usage to KV
  await incrementMonthlyUsage(user.id, tokensUsed, env);

  return json(result, 200, {
    'X-WAM-Tokens-Used':      String(used + tokensUsed),
    'X-WAM-Tokens-Remaining': String(Math.max(0, plan.monthly_token_budget - used - tokensUsed)),
    'X-WAM-Reset-At':         nextMonthStart(),
  });
}

// ─── Usage ───────────────────────────────────────────────────────────────────

async function handleUsage(request, env) {
  const user = await authenticate(request, env);
  if (!user) return json({ error: 'unauthorized' }, 401);

  const used = await getMonthlyUsage(user.id, env);
  const plan = PLAN_DEFINITIONS[user.plan];

  return json({
    tokens_used:      used,
    tokens_remaining: Math.max(0, plan.monthly_token_budget - used),
    monthly_budget:   plan.monthly_token_budget,
    resets_at:        nextMonthStart(),
  });
}

// ─── LemonSqueezy Webhook ─────────────────────────────────────────────────────

async function handleLSWebhook(request, env) {
  // Verify HMAC signature from LemonSqueezy
  const signature = request.headers.get('X-Signature');
  const body      = await request.text();
  const valid     = await verifyLSSignature(body, signature, env.LEMONSQUEEZY_WEBHOOK_SECRET);
  if (!valid) return json({ error: 'invalid_signature' }, 401);

  const event = JSON.parse(body);
  const email = event.data?.attributes?.user_email;

  switch (event.meta.event_name) {
    case 'subscription_created':
    case 'subscription_resumed':
      await updateUserPlan(email, 'pro', null, env);
      break;
    case 'subscription_cancelled':
    case 'subscription_expired':
      await updateUserPlan(email, 'free', null, env);
      break;
    case 'order_created':
      // One-time purchase if applicable
      await updateUserPlan(email, 'pro', null, env);
      break;
  }

  return json({ received: true });
}

// ─── Rate limit helpers (Cloudflare KV) ──────────────────────────────────────

function currentMonthKey(userId) {
  const now = new Date();
  return `usage:${userId}:${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

async function getMonthlyUsage(userId, env) {
  const val = await env.USAGE_KV.get(currentMonthKey(userId));
  return parseInt(val || '0');
}

async function incrementMonthlyUsage(userId, tokens, env) {
  const key  = currentMonthKey(userId);
  const used = await getMonthlyUsage(userId, env); // acceptable: KV reads are fast
  await env.USAGE_KV.put(key, String(used + tokens), {
    expiration: nextMonthStartUnix()
  });
}

function nextMonthStart() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth() + 1, 1).toISOString();
}

function nextMonthStartUnix() {
  const d = new Date();
  return Math.floor(new Date(d.getFullYear(), d.getMonth() + 1, 1).getTime() / 1000);
}

// ─── Plan definitions ─────────────────────────────────────────────────────────

const PLAN_DEFINITIONS = {
  free: {
    monthly_token_budget: 50000,
    features: {
      proxy_api_access:     true,
      model:                'claude-haiku-4-5',
      streaming:            false,
      max_output_tokens:    1000,
      conversation_memory:  false,
      bulk_generation:      false,
      own_api_key:          false,
    }
  },
  trial: {
    monthly_token_budget: 300000,
    features: {
      proxy_api_access:     true,
      model:                'claude-haiku-4-5',
      streaming:            false,
      max_output_tokens:    2000,
      conversation_memory:  true,
      bulk_generation:      false,
      own_api_key:          false,
    }
  },
  pro: {
    monthly_token_budget: Infinity, // Pro uses own key — budget is irrelevant
    features: {
      proxy_api_access:     false,  // Pro routes direct to provider, not through proxy
      model:                null,   // User's choice
      streaming:            true,
      max_output_tokens:    null,   // Unlimited
      conversation_memory:  true,
      bulk_generation:      true,
      own_api_key:          true,
    }
  }
};

// ─── Utilities ────────────────────────────────────────────────────────────────

async function authenticate(request, env) {
  const auth  = request.headers.get('Authorization') || '';
  const token = auth.replace('Bearer ', '');
  if (!token) return null;
  return verifyJWT(token, env.JWT_SECRET);
}

function json(data, status = 200, extraHeaders = {}) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json', ...extraHeaders }
  });
}

// verifyJWT, signJWT, verifyCredentials, getUserById, updateUserPlan,
// verifyLSSignature — implement using Web Crypto API (available in CF Workers)
```

### Deployment

```bash
# One-time setup
npm install -g wrangler
wrangler login
wrangler kv:namespace create USAGE_KV   # note the ID, add to wrangler.toml

# Set secrets (stored encrypted by Cloudflare — never in code)
wrangler secret put ANTHROPIC_API_KEY
wrangler secret put JWT_SECRET
wrangler secret put LEMONSQUEEZY_WEBHOOK_SECRET

# Development
wrangler dev                             # local dev at localhost:8787

# Deploy
wrangler deploy
# → live at: https://wp-ai-mind-proxy.your-account.workers.dev
# → attach custom domain (api.wpaimind.com) in CF dashboard
```

---

## WordPress Plugin Changes

### New PHP architecture

The plugin's responsibility narrows to: auth management, request dispatch, response rendering, and usage display.

**New classes / files needed:**

```
wp-ai-mind/
└── includes/
    ├── Auth/
    │   ├── class-nj-auth.php          # Token storage, refresh, logout
    │   └── class-nj-auth-ui.php       # Login/signup screen in WP
    ├── Entitlement/
    │   └── class-nj-entitlement.php   # Fetch + cache entitlement doc
    ├── Proxy/
    │   └── class-nj-proxy-client.php  # HTTP client for your API
    └── Admin/
        └── class-nj-usage-widget.php  # Dashboard usage meter UI
```

**Classes to remove or gut:**

```
ProGate              → delete entirely
UsageLogger          → delete (logging moves to Worker)
FreemiusProvider     → delete (entire Freemius integration)
[Provider]ApiKey     → retain for Pro users only (see below)
```

### `class-nj-auth.php`

```php
<?php

class NJ_Auth {

    const TOKEN_OPTION   = 'wam_auth_token';
    const REFRESH_OPTION = 'wam_refresh_token';
    const API_BASE       = 'https://api.wpaimind.com/v1';

    public static function get_token(): string|null {
        $token = get_option( self::TOKEN_OPTION );
        if ( ! $token ) return null;

        // Decode without verification to check expiry
        $parts   = explode( '.', $token );
        $payload = json_decode( base64_decode( $parts[1] ?? '' ), true );

        if ( isset( $payload['exp'] ) && $payload['exp'] < time() + 60 ) {
            $token = self::refresh_token();
        }

        return $token;
    }

    public static function login( string $email, string $password ): bool {
        $response = wp_remote_post( self::API_BASE . '/auth/token', [
            'body'    => wp_json_encode( compact( 'email', 'password' ) ),
            'headers' => [ 'Content-Type' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) return false;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) return false;

        update_option( self::TOKEN_OPTION,   $body['access_token'] );
        update_option( self::REFRESH_OPTION, $body['refresh_token'] );

        // Bust entitlement cache on login
        delete_transient( 'wam_entitlement' );

        return true;
    }

    public static function logout(): void {
        delete_option( self::TOKEN_OPTION );
        delete_option( self::REFRESH_OPTION );
        delete_transient( 'wam_entitlement' );
    }

    public static function is_authenticated(): bool {
        return ! empty( self::get_token() );
    }

    private static function refresh_token(): string|null {
        $refresh = get_option( self::REFRESH_OPTION );
        if ( ! $refresh ) return null;

        $response = wp_remote_post( self::API_BASE . '/auth/refresh', [
            'body'    => wp_json_encode( [ 'refresh_token' => $refresh ] ),
            'headers' => [ 'Content-Type' => 'application/json' ],
        ] );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) return null;

        update_option( self::TOKEN_OPTION, $body['access_token'] );
        return $body['access_token'];
    }
}
```

### `class-nj-entitlement.php`

```php
<?php

class NJ_Entitlement {

    const TRANSIENT_KEY = 'wam_entitlement';
    const TTL           = HOUR_IN_SECONDS;

    public static function get(): array|null {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached ) return $cached;

        $token = NJ_Auth::get_token();
        if ( ! $token ) return null;

        $response = wp_remote_get( NJ_Auth::API_BASE . '/entitlement', [
            'headers' => [ 'Authorization' => 'Bearer ' . $token ],
        ] );

        if ( is_wp_error( $response ) ) return null;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['plan'] ) ) return null;

        set_transient( self::TRANSIENT_KEY, $data, self::TTL );
        return $data;
    }

    public static function can( string $feature ): bool {
        $entitlement = self::get();
        return (bool) ( $entitlement['features'][ $feature ] ?? false );
    }

    public static function is_plan( string ...$plans ): bool {
        $entitlement = self::get();
        return in_array( $entitlement['plan'] ?? '', $plans, true );
    }

    public static function tokens_remaining(): int {
        $entitlement = self::get();
        return (int) ( $entitlement['usage']['tokens_remaining'] ?? 0 );
    }

    public static function bust_cache(): void {
        delete_transient( self::TRANSIENT_KEY );
    }
}
```

### `class-nj-proxy-client.php`

```php
<?php

class NJ_Proxy_Client {

    public static function chat( array $messages, array $options = [] ): array|WP_Error {
        $token = NJ_Auth::get_token();
        if ( ! $token ) {
            return new WP_Error( 'unauthorized', __( 'Not authenticated.', 'wp-ai-mind' ) );
        }

        $response = wp_remote_post( NJ_Auth::API_BASE . '/chat', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( array_merge( [ 'messages' => $messages ], $options ) ),
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Update local usage display from response headers
        $remaining = wp_remote_retrieve_header( $response, 'x-wam-tokens-remaining' );
        if ( $remaining !== '' ) {
            $cached = get_transient( NJ_Entitlement::TRANSIENT_KEY );
            if ( $cached ) {
                $cached['usage']['tokens_remaining'] = (int) $remaining;
                set_transient( NJ_Entitlement::TRANSIENT_KEY, $cached, NJ_Entitlement::TTL );
            }
        }

        if ( $code === 429 ) {
            return new WP_Error(
                'limit_exceeded',
                __( 'Monthly AI credit limit reached.', 'wp-ai-mind' ),
                [ 'upgrade_url' => $body['upgrade_url'] ?? '' ]
            );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', $body['error'] ?? 'Unknown error', [ 'code' => $code ] );
        }

        return $body;
    }
}
```

### Pro users: own API key flow

Pro users bypass the proxy entirely. Their own API key is stored encrypted in `wp_options` using `AUTH_KEY` from `wp-config.php` as the encryption key.

```php
function nj_encrypt_api_key( string $key ): string {
    $iv        = random_bytes( 16 );
    $encrypted = openssl_encrypt( $key, 'AES-256-CBC', AUTH_KEY, 0, $iv );
    return base64_encode( $iv . '::' . $encrypted );
}

function nj_decrypt_api_key( string $stored ): string {
    [ $iv, $encrypted ] = explode( '::', base64_decode( $stored ) );
    return openssl_decrypt( $encrypted, 'AES-256-CBC', AUTH_KEY, 0, $iv );
}
```

Routing logic (replaces `ProGate::is_pro()`):

```php
function nj_resolve_provider(): string {
    if ( NJ_Entitlement::can( 'own_api_key' ) ) {
        // Pro: route directly to user's configured provider
        return nj_get_user_configured_provider();
    }

    if ( NJ_Entitlement::can( 'proxy_api_access' ) ) {
        // Free / Trial: route through proxy
        return 'plugin_proxy';
    }

    // Not authenticated or no eligible plan
    return '';
}
```

---

## Payment & Licensing: LemonSqueezy

**Why LemonSqueezy over Freemius:**
- Cleaner, modern API with reliable webhooks
- Handles EU VAT automatically
- No WordPress coupling — works for any SaaS/plugin
- Better developer experience, lower complexity than building on EDD

**Integration points:**

```
1. Checkout: Embed LemonSqueezy checkout overlay in plugin upgrade CTA
   → Use LS overlay JS: opens checkout without leaving wp-admin

2. Webhook → Cloudflare Worker (/webhooks/lemonsqueezy):
   → subscription_created / order_created → set plan = 'pro'
   → subscription_cancelled / subscription_expired → set plan = 'free'
   → Worker verifies HMAC signature before processing

3. Plugin polls /entitlement every hour (transient TTL)
   → Plan change propagates within 1 hour automatically
   → Or: bust cache on next plugin load if plan_expires has passed
```

**Free signup (no payment):**

```
1. User visits wpaimind.com/signup
   OR plugin shows inline signup form (email + password only)
2. Account created → plan = 'trial' for 7 days → then plan = 'free'
3. Zero friction — no credit card, no license key
```

---

## Rate Limiting Architecture

### Where limits are enforced

| Layer | What it does | Why |
|---|---|---|
| **Cloudflare Worker** | Checks KV before forwarding to Anthropic | Authoritative — cannot be bypassed |
| **Plugin UI** | Shows usage meter, disables button at 0 remaining | UX — prevents wasted requests |
| **Anthropic console** | Hard monthly spend cap on your proxy key | Circuit breaker — catches bugs in Worker logic |

### Rate limit dimensions

| Dimension | Value | Notes |
|---|---|---|
| Identity unit | `user_id` (not install_id) | Prevents multi-install abuse |
| Time window | Rolling calendar month | Resets at midnight UTC on 1st |
| Metric | Input + output tokens combined | Maps directly to your cost |
| Max per request | 1,000 tokens (free) / 2,000 (trial) | Prevents single runaway response |
| Concurrency | 1 in-flight request per user_id | Prevents parallel request abuse |

### Abuse mitigations

| Vector | Mitigation |
|---|---|
| Multiple WP installs, same account | Rate limit on `user_id`, not `install_id` |
| Prompt engineering to maximise output | Hard `max_tokens` cap enforced in Worker |
| Rapid-fire requests | Secondary: 10 requests/minute limit per user |
| Replay attacks | JWT expiry (1 hour) + nonce if needed |
| Credential sharing | Treat as acceptable at small scale; add device fingerprinting if it becomes an issue |

---

## UX: Usage Display & Upgrade Flow

### In-plugin usage meter

Display on every relevant screen (chat interface, content generation panel):

```
[██████░░░░] 37,550 / 50,000 credits remaining
Resets May 1 · Upgrade for unlimited →
```

Data sourced from: `NJ_Entitlement::get()['usage']` (cached) + `X-WAM-Tokens-Remaining` header (live, updated per request).

### Limit hit messaging

**At 80% used — inline notice (dismissible):**
> "You've used 80% of your free AI credits this month. Upgrade to Pro for unlimited requests with your own API key."

**At 100% — blocks request, shows modal:**
> "You've reached your free limit for May.
> This month you generated [X content pieces] and had [Y chat exchanges].
> Your credits reset June 1, or upgrade now to continue without interruption."
>
> [Upgrade to Pro — from $X/mo]   [Wait until June 1]

**Key UX principles:**
- Never block the entire plugin UI — only the AI request action
- Always show what the user accomplished (not just what they hit)
- Always show the reset date as a real alternative to upgrade (builds trust)
- Upgrade CTA opens LemonSqueezy overlay — no page navigation

---

## Cost Model & Risk Controls

### Projected shared key cost (Claude Haiku)

Pricing: ~$0.80/M input tokens, ~$4/M output tokens (blended: ~$1.50/M)

| Free users | Avg tokens/month | Monthly cost |
|---|---|---|
| 100 | 30k | ~$4.50 |
| 500 | 30k | ~$22.50 |
| 1,000 | 30k | ~$45 |

This is customer acquisition cost — roughly $0.04–0.05/user/month.

### Cost control layers

```
1. Model: Claude Haiku for all free/trial requests (cheapest capable model)
2. Worker: Hard token cap enforced before forwarding to Anthropic
3. Anthropic console: Set hard monthly spend limit at 150% of projected cost
4. Monitoring: Alert at 2× expected hourly burn; auto-pause at 5× (circuit breaker)
```

---

## Codebase Exploration Notes for New Chat

When starting implementation, the new chat should:

1. **Read `CLAUDE.md`** in the project root for existing conventions and rules
2. **Locate and read `ProGate`** — understand all call sites before removing
3. **Locate and read `UsageLogger`** — understand schema before removing (may inform Worker's KV key design)
4. **Locate all `wam_fs()` calls** — each is a migration point to `NJ_Auth` or `NJ_Entitlement`
5. **Locate the existing provider abstraction** — `NJ_Proxy_Client` plugs in as a new provider, not a replacement for the provider pattern
6. **Check existing REST API endpoints** — the plugin may already have endpoints that need auth updated
7. **Check admin settings pages** — API key settings UX needs redesign for free vs. pro flows

The Cloudflare Worker is a greenfield build — no existing code to reference there.

---

## Summary: Key Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Plugin role | Thin client only | Enforcement in plugin can be bypassed; market has converged on this |
| Identity | Account-based + JWT | Frictionless free signup; no license key friction |
| Payment | LemonSqueezy | Best Freemius alternative; EU VAT handled; clean webhooks |
| Proxy infra | Cloudflare Worker | Zero server management; free at low volume; instant global deploy |
| Rate limit storage | Cloudflare KV | Co-located with Worker; no extra infra |
| Rate limit identity | user_id (not install_id) | Prevents multi-install abuse |
| Rate limit metric | Tokens (not requests) | Maps to actual cost |
| Free tier model | Claude Haiku | Cheapest capable model; qualitative upgrade incentive to better models |
| Free tier cap | 50k tokens/month | ~25 exchanges; feels generous; power users hit ceiling |
| Trial cap | 300k tokens/month | Full product experience before trial ends |
| Pro routing | Direct to provider (own key) | Pro users bypass proxy entirely; you pay nothing for their usage |
| Pro key storage | AES-256 with AUTH_KEY | Practical standard; weak only if wp-config.php is already compromised |
| Freemius | Removed entirely | Replaced by LemonSqueezy + custom JWT auth |
| Upgrade trigger | At limit: show accomplishments then CTA | Showing value before ask converts better than pure friction |
