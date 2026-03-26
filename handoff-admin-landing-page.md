# Handoff: WP AI Mind Admin Landing Page

## Task

Design and implement a **state-of-the-art 2026 landing/dashboard page** for the WP AI Mind WordPress plugin admin interface.

Currently, when a user navigates to the plugin's admin area, they land directly in the chat interface with no context, welcome screen, or orientation. This is a poor user experience — there is no landing page.

The goal is to design and build a polished, modern landing/dashboard page that serves as the entry point before the user dives into chat.

---

## Skills to Invoke (in order)

1. `superpowers:brainstorming` — **resume from clarifying questions** (context exploration is complete, see below)
2. `frontend-design` — for implementation guidance after the spec is approved
3. `superpowers:dispatching-parallel-agents` — for parallel implementation if needed

---

## Where We Left Off

The brainstorming skill was invoked and context exploration (Step 1) is **complete**. The next step is:

> **Step 2 of brainstorming**: Offer the visual companion (browser-based mockup tool) — this must be its own message before asking clarifying questions.

Then proceed through the brainstorming checklist:
- [ ] Offer visual companion (own message, no other content)
- [ ] Ask clarifying questions (one at a time)
- [ ] Propose 2-3 approaches with trade-offs
- [ ] Present design sections, get approval
- [ ] Write design doc to `docs/superpowers/specs/YYYY-MM-DD-admin-landing-page-design.md`
- [ ] Spec self-review
- [ ] User reviews spec
- [ ] Invoke `writing-plans` skill

---

## Codebase Context

### Repository
- Path: `/Users/niklas/Documents/Homepages/wp-ai-mind`
- Plugin namespace: `WP_AI_Mind\`
- Active branch: `main` (all code changes must go through PRs — never commit directly to `main`)

### What WP AI Mind does
An AI assistant plugin for WordPress. Provides:
- **Chat interface** — conversational AI assistant for content creators
- **Settings** — provider config (API keys, model selection), voice, features
- **Usage dashboard** — token/cost analytics
- **Generator** — post/page generation workflow
- **Editor integration** — Gutenberg block sidebar

### Current Admin Structure
Two separate React apps mount to different DOM elements on separate WP admin pages:

| App | Mount point | Page |
|-----|-------------|------|
| `ChatApp` | `#wp-ai-mind-chat` | Main plugin page |
| `SettingsApp` | `#wp-ai-mind-settings` | Settings sub-page |

There is also a `UsageDashboard` on its own page.

**The problem:** `ChatApp` is the first thing users see. There is no welcome screen, no overview, no call-to-action.

### Source Directory Structure
```
src/
  admin/
    components/
      Chat/
        ChatApp.jsx          ← Three-panel chat UI (root component)
        ChatApp includes EmptyState, sidebar, composer, right panel
    settings/
      SettingsApp.jsx        ← Tab-based settings (Providers, Voice, Features)
    index.js                 ← Entry point, mounts ChatApp + SettingsApp
    admin.css                ← Main stylesheet (~662 lines)
  editor/                    ← Gutenberg integration
  frontend/                  ← Public widget
  generator/                 ← Content generation workflow
  usage/
    components/
      UsageDashboard.jsx     ← Stats cards + sparkline chart
    usage.css
  shared/
    components/
      MarkdownContent.jsx
  styles/
    tokens.css               ← Design tokens (see below)
```

### Design System (tokens.css)

**Colour palette — dark theme only (zinc scale)**
```css
--color-bg:              #09090b;   /* Page background */
--color-surface:         #18181b;   /* Card/panel */
--color-surface-2:       #27272a;   /* Hover/input */
--color-border:          #3f3f46;
--color-border-subtle:   #27272a;

--color-text-primary:    #fafafa;
--color-text-secondary:  #a1a1aa;
--color-text-muted:      #52525b;

--color-accent:          #2563eb;   /* blue-600 */
--color-accent-hover:    #1d4ed8;
--color-accent-subtle:   rgba(37,99,235,0.12);
--color-accent-border:   rgba(37,99,235,0.3);

--color-success:         #16a34a;
--color-warning:         #d97706;
--color-error:           #dc2626;
```

**Spacing:** `--space-1` (4px) → `--space-10` (40px)

**Radius:** `--radius-sm: 4px` · `--radius: 6px` · `--radius-md: 8px` · `--radius-lg: 12px`

**Typography:** Inter (sans-serif), JetBrains Mono (monospace), sizes `--text-xs` (11px) → `--text-xl` (20px)

### Key Dependencies
- `lucide-react` v0.474.0 — icons (MessageSquare, Key, Mic, Zap, BarChart2, etc.)
- `@wordpress/element` — React rendering
- `@wordpress/api-fetch` — API client
- `marked` — markdown parsing
- Build: `@wordpress/scripts` (wp-scripts) with custom webpack

### Existing UI Patterns to Reuse
- `.wpaim-feature-card` — settings card grid (280px min-width)
- `.wpaim-usage__stat-card` — stats card (3-column grid)
- `.wpaim-pro-badge` — "Pro" label badge
- `.wpaim-btn--primary` / `--ghost` / `--icon`
- `.wpaim-panel-section` / `.wpaim-panel-label`

### Coding Conventions
- Function prefix: `nj_` (PHP); component files use PascalCase
- British English in content/comments
- All user-facing strings must be translatable via WP i18n functions
- CSS: wrap selectors in `:root :where()`, use existing token variables, never hardcode colours
- Feature branch required before any code: `git checkout -b feat/admin-landing-page`

---

## WordPress / Deployment Context

| Environment | URL | Notes |
|-------------|-----|-------|
| Local | localhost:8080 | Docker, full DB access |
| Staging | staging4.blog.njohansson.eu | SSH alias: `siteground-staging` |
| Production | blog.njohansson.eu | Manual deploy only |

Local admin credentials (Docker only): `nj_agent` / `C8IcqAWJu8F3dOw6E4ndWhIe`

Build command: `npm run build` (run from plugin root)
OPcache note: restart Docker container after PHP edits — `docker restart blognjohanssoneu-wordpress-1`

---

## Open Questions for Brainstorming

These have NOT been asked yet. Work through them one at a time:

1. Should the landing page replace what users see when they first open the plugin (i.e., a new "Home" tab before chat), or should it be a separate sub-page?
2. What is the primary audience — WordPress authors/editors, or site admins configuring the plugin?
3. Should the landing page show live data (recent conversations, usage stats), or is it primarily a static welcome/navigation hub?
4. Are there any reference designs or products the user wants to draw inspiration from?
5. Should it gate any sections behind a "Pro" badge if the user doesn't have a licence key configured?
