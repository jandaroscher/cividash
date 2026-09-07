# Contributing to CiviDash

Thank you for considering a contribution to CiviDash.

## Getting Started

The project runs on [DDEV](https://ddev.com/). Node.js 20 or newer is required
outside of DDEV (see `.nvmrc`, `nvm use`).

```bash
ddev start
ddev exec composer install
ddev exec npm install
ddev exec php artisan migrate
ddev exec php artisan tenancy:backfill
ddev exec php artisan db:seed --class=TenantSeeder
```

For local development with hot reload:

```bash
composer run dev
```

See `AGENTS.md` in the repository root for the full architecture overview and command
reference.

## Branching & Commits

- Branch naming: `feat/short-description`, `fix/short-description`,
  `chore/short-description`. Optionally prefix the description with the number of an
  issue in the public tracker, e.g. `fix/123-short-description`.
- Commit message format: `[FEAT|FIX|CHORE|REFACTOR|TEST|DOCS] Description`

## Development Process

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

- Never hardcode user-facing strings — use translation keys (`de`/`en`) as described in
  `CLAUDE.md`.
- Do not commit debug code (`dd()`, `console.log`) or secrets.

## Submitting Changes

1. Fork or branch, make your change, and ensure tests and linters pass.
2. Open a pull/merge request describing the change and linking the related ticket, if
   any.
3. Pull requests are reviewed by [CodeRabbit](https://coderabbit.ai) as well as human
   maintainers — please address all review comments before merge.
4. All tests must be green and there must be no open review comments before a PR is
   merged.

## Contributor Agreement

confirming you have the right to submit the change under the project's license:

```bash
git commit -s
```

This adds a `Signed-off-by` trailer to your commit message. See
[developercertificate.org](https://developercertificate.org/) for the full text.

## Code of Conduct

By participating in this project, you agree to abide by the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting Security Issues

Please do not report security vulnerabilities through public issues. See
[SECURITY.md](SECURITY.md) for the responsible disclosure process.
