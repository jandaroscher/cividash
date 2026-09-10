# Contributing to CiviDash

Thank you for considering a contribution to CiviDash.

## Getting started

The project runs on [DDEV](https://ddev.com/). Node.js 20 or newer is required
outside of DDEV (see `.nvmrc`, `nvm use`).

```bash
ddev start
ddev exec composer install
ddev exec npm install
ddev exec php artisan migrate
ddev exec php artisan tenancy:backfill
ddev exec php artisan db:seed
```

For local development with hot reload:

```bash
composer run dev
```

See `AGENTS.md` in the repository root for the full architecture overview and command
reference.

## Branching and commits

- Branch naming: `feat/short-description`, `fix/short-description`,
  `chore/short-description`. Optionally prefix the description with the number of an
  issue in the public tracker, e.g. `fix/123-short-description`.
- Commit message format (Conventional Commits): `type(scope)?: description`.
  Types: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`, `perf`. Breaking
  changes use `type!:` and add a `BREAKING CHANGE:` footer, e.g.
  [release-please](https://github.com/googleapis/release-please) derives the version
  and CHANGELOG from these commits (`feat` -> minor, `fix` -> patch, `!` -> major).
  PRs are squash-merged, so the PR title becomes the commit message and must follow
  this format.

## Development process

- Work is generally test-driven: write or update tests alongside the change.
- Run only the tests affected by your change during development, then the full suite
  before opening a pull request:

  ```bash
  ddev exec php artisan test                                   # full backend suite
  ddev exec php artisan test --filter=SomeTestClass             # single test class
  npm run test:e2e                                               # Playwright E2E tests
  npx vitest run tests/js/components/SomeComponent.test.js       # single JS test file
  ```

- Code style:

  ```bash
  ddev exec vendor/bin/pint --test   # PHP formatting check (Laravel Pint)
  ddev exec vendor/bin/pint          # PHP formatting fix (Laravel Pint)
  npm run lint:fix                   # ESLint fix for Vue/JS
  ```

- Never hardcode user-facing strings; use translation keys (`de`/`en`) as described in
  `AGENTS.md`.
- Do not commit debug code (`dd()`, `console.log`) or secrets.

## Before you open a PR

Run these locally and make sure they pass:

```bash
ddev exec vendor/bin/pint --test   # PHP formatting check
npm run lint                       # ESLint for Vue/JS
ddev exec php artisan test         # Backend test suite
npx vitest run                     # Frontend test suite
```


## Submitting changes

1. Fork or branch, make your change, and ensure tests and linters pass.
2. Open a pull/merge request describing the change and linking the related ticket, if
   any.
3. Pull requests are reviewed by [CodeRabbit](https://coderabbit.ai) and by human
   maintainers. Address all review comments before merge.
4. All tests must be green and there must be no open review comments before a PR is
   merged.

## Developer Certificate of Origin

confirming you have the right to submit the change under the project's license:

```bash
git commit -s
```

This adds a `Signed-off-by` trailer to your commit message. See
[developercertificate.org](https://developercertificate.org/) for the full text.

## Code of conduct

By participating in this project, you agree to abide by the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting security issues

Please do not report security vulnerabilities through public issues. See
[SECURITY.md](SECURITY.md) for the responsible disclosure process.
