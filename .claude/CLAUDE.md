# WP AI Mind — Repo-Specific Agent Instructions

> This file adds rules specific to the `niklas-joh/wp-ai-mind` repository.
> It extends (and does not replace) the shared WordPress profile in `CLAUDE.md` → `.agents/profiles/wordpress/AGENTS.md`.

---

## Local Setup (fresh clone)

```bash
npm install   # installs dependencies AND the pre-commit hook via the prepare script
composer install
```

The pre-commit hook (`scripts/pre-commit`) automatically runs `npm run build` and stages
the compiled `assets/` whenever `src/` files are committed. No manual build step needed.

---

## Git & GitHub Workflow

### Branch Protection — Never Commit to `main`

`main` is a protected branch. **All changes must go through a pull request.**

- Always create a feature branch before writing any code:
  ```bash
  git checkout -b feat/short-description   # new feature
  git checkout -b fix/short-description    # bug fix
  git checkout -b chore/short-description  # maintenance
  ```
- Never run `git push origin main` directly.
- Never use `git commit --amend` on commits that have already been pushed to a remote branch.

### Pull Request Rules

- Every PR targets `main` (default base branch).
- PR title must follow Conventional Commits: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`.
- Include a short summary and a test plan in the PR body.
- Request review before merging — do not self-merge without explicit user instruction.

### Commit Message Convention

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<optional scope>): <short summary>

[optional body]
```

Examples:
- `feat(chat): add streaming response support`
- `fix(api): handle missing API key gracefully`
- `chore: bump version to 0.3.0`

---

## Release Process

See `RELEASING.md` for the full release checklist.
Agents must never trigger a release without explicit user instruction.
