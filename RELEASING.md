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

## Automated Feature-Grouping Workflow

The standard path from `develop` → `main` is fully automated except two deliberate human decisions: applying the `release-ready` label to a PR, and approving the final release PR.

### One-time repo setup

Run once (requires a token with repo admin scope) to switch `develop` PRs from squash-merge to merge commits, which preserves the source branch name in history:

```bash
gh api repos/niklas-joh/wp-ai-mind \
  --method PATCH \
  --field allow_squash_merge=false \
  --field allow_rebase_merge=false \
  --field allow_merge_commit=true \
  --field merge_commit_title=PR_TITLE \
  --field merge_commit_message=PR_BODY
```

### Automated chain

```
feat/* branch
  → PR to develop — CI + code review run automatically
  → Apply release-ready label to the PR (any time — before or after merge)
  → PR approved + merged (merge commit)
  → tag-feature-merge.yml fires automatically:
       creates annotated tag  merged/<branch-name>
       annotation stores the PR number for later lookup

When ready to cut a release:
  → Run workflow_dispatch on "Build Release Branch"
       Finds all merged/* tags whose PR has release-ready label
       Creates release/vX.Y.Z from main, cherry-picks those commits
       Runs semantic-release --dry-run to derive the version
       Updates wp-ai-mind.php (×2), readme.txt, package.json, CHANGELOG.md
       Commits "chore: release vX.Y.Z"
       Opens PR to main

  → Review and approve the release PR  ← only human step
  → PR merged to main

  → tag-release-merge.yml fires automatically:
       creates vX.Y.Z tag at the merge commit
  → release.yml fires automatically (triggered by tag):
       builds plugin zip, publishes GitHub Release
```

### The `release-ready` label

Apply the `release-ready` label to a PR targeting `develop` to include it in the next release. The label can be added or removed at any time — the release builder re-reads it when triggered.

A PR without `release-ready` is still merged to `develop` normally and tagged with `merged/*`; it simply won't be included until the label is applied.

### Tag conventions

| Tag pattern | Created by | Purpose |
|---|---|---|
| `merged/<branch-name>` | `tag-feature-merge.yml` | Feature reference; used by release builder |
| `v0.3.0`, `v0.3.0-beta.1` | `tag-release-merge.yml` | Triggers zip build + GitHub Release |

### Emergency hotfix releases (bypass `develop`)

For critical hotfixes that must go to `main` without going through `develop`, use the manual checklist below. Branch off `main` directly as `fix/short-description`, fix, PR to `main`, then backport to `develop`.

---

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
| `release/vx.y.z` | Release prep branch built automatically by `build-release-branch.yml` |

PRs must pass CI (PHPCS + PHPUnit + JS lint) before merge.

## Tag Convention

Tags use `v` prefix + semver: `v0.2.0`, `v0.2.0-beta.1`, `v0.2.0-rc.1`

Beta/RC tags → GitHub Release marked as pre-release (no WP.org submission)
Stable tags → GitHub Release + WP.org SVN submission
