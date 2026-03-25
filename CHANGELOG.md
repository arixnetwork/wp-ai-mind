# Changelog

All notable changes to WP AI Mind are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning: [Semantic Versioning](https://semver.org/).

## [0.2.0] — 2026-03-25

### Added
- Dedicated GitHub repository (`niklas-joh/wp-ai-mind`) extracted from blog monorepo
- GitHub Actions CI pipeline (PHPCS, PHPUnit, JS/CSS lint) on push/PR to main and develop
- GitHub Actions Release workflow — builds WP.org zip and creates GitHub Release on semver tags
- `RELEASING.md` — semver convention and release checklist
- Regenerated `languages/wp-ai-mind.pot` from source (was a stub)

### Fixed
- `bin/build-wporg.sh` now includes production `vendor/` in the distribution zip (Freemius SDK was missing from built zip)

## [0.1.0] — 2026-03-25

### Added
- Initial release.
- Chat assistant with multi-provider support (Claude, OpenAI, Gemini, Ollama).
- Content generator module with tone and length controls.
- SEO metadata generator (Pro).
- Image generation module (Pro).
- Usage dashboard with per-provider token tracking.
- Frontend chat widget via `[wp_ai_mind_chat]` shortcode.
- Gutenberg sidebar integration.
- Tool-calling support for post creation and editing.
- REST API v1 with capability-gated endpoints.
