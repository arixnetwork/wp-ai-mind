# Release Process

## Versioning (Semantic Versioning)

Format: `MAJOR.MINOR.PATCH`

| Increment | When |
|-----------|------|
| PATCH (`0.2.1`) | Bug fixes only, no new features |
| MINOR (`0.3.0`) | New features, backwards-compatible changes |
| MAJOR (`1.0.0`) | Breaking changes, major rewrites |

Pre-release suffixes: `-beta.1`, `-rc.1` (e.g. `v0.3.0-beta.1`)

`0.x.y` = development phase (before WP.org stable submission)
`1.0.0` = first stable WP.org release

## Release Checklist

1. Bump version in 4 files: `wp-ai-mind.php` (×2), `readme.txt`, `package.json`
2. Update `CHANGELOG.md` — add new `[X.Y.Z] — YYYY-MM-DD` entry
3. Update `readme.txt` Changelog section to match
4. Regenerate POT: `wp @local i18n make-pot . languages/wp-ai-mind.pot --domain=wp-ai-mind --exclude=.github,node_modules,vendor,dist,tests`
5. Verify: `./vendor/bin/phpcs --standard=phpcs.xml.dist && ./vendor/bin/phpunit tests/Unit/`
6. Commit: `git commit -m "chore: release vX.Y.Z"`
7. Push: `git push origin main`
8. Tag: `git tag vX.Y.Z && git push origin vX.Y.Z`
9. GitHub Actions builds the zip and creates the GitHub Release automatically
10. **For stable WP.org releases only:** Download the zip from the GitHub Release and submit via WP.org SVN

## Branch Strategy

| Branch | Purpose |
|--------|---------|
| `main` | Production-ready, always deployable |
| `develop` | Integration branch for next release |
| `feature/xxx` | Feature branches, merge to `develop` via PR |
| `release/x.y.z` | Optional release prep branch off `develop` |

PRs must pass CI (PHPCS + PHPUnit + JS lint) before merge.

## Tag Convention

Tags use `v` prefix + semver: `v0.2.0`, `v0.2.0-beta.1`, `v0.2.0-rc.1`

Beta/RC tags → GitHub Release marked as pre-release (no WP.org submission)
Stable tags → GitHub Release + WP.org SVN submission
