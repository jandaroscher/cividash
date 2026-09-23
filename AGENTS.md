# AGENTS.md

Guidance for AI coding agents (e.g. Claude Code, Codex) working in this repository. It
covers conventions only; [CONTRIBUTING.md](CONTRIBUTING.md) is the authoritative guide
for contributors and wins on any conflict.

## Setup

Run all PHP, Composer and Artisan commands through DDEV:

```bash
ddev start                              # Start the environment
ddev exec composer install              # Install PHP dependencies
ddev exec php artisan key:generate      # Generate the app key (DDEV creates .env)
ddev exec npm install                   # Install Node dependencies
ddev exec php artisan migrate           # Run migrations
ddev exec php artisan tenancy:backfill  # Create the default tenant
ddev exec php artisan db:seed           # Seed demo data (tenants, roles)
ddev exec npm run build                 # Build frontend assets (public routes need the Vite manifest)
```

The one exception is `composer run dev` (Laravel server, queue, logs and Vite with hot
reload). It runs on the host and needs local PHP and Node.js.

The database is PostgreSQL 16. DDEV cannot switch the engine of an existing project, so
recreate an old MariaDB project once: `ddev delete --omit-snapshot && ddev start`.

For a second checkout (git worktree), run `bash scripts/setup-worktree-ddev.sh [suffix]`.
It creates a separate DDEV project with its own database.

## Tests and code quality

Run only the affected tests while working, the full suite before you commit:

```bash
ddev exec php artisan test --filter=ManageSiteSettingsTest   # Single test class
ddev exec php artisan test tests/Feature/Api/                # Single directory
npx vitest run tests/js/components/Header.test.js            # Single JS test file
ddev exec php artisan test                                   # Full backend suite
npx vitest run                                               # Full frontend suite
npm run test:e2e                                             # Playwright E2E tests
ddev exec vendor/bin/pint --test                             # PHP formatting check
ddev exec vendor/bin/pint                                    # PHP formatting fix
npm run lint                                                 # ESLint check
npm run lint:fix                                             # ESLint fix
```

Write tests first (TDD).

## Architecture

- Headless SPA: Laravel 12 serves a REST API and the Filament admin panel; Vue 3 renders
  all public routes client-side. `resources/views/app.blade.php` boots the SPA, and the
  Laravel catch-all route hands everything except `/api/*`, `/admin/*` and `/filament/*`
  to Vue Router.
- Content blocks: Filament Fabricator provides the page builder. Blocks live in
  `app/Filament/Fabricator/PageBlocks/`; `BlockRenderer.vue` renders them.
- Key directories: `app/Filament/` (admin), `app/Services/` (business logic),
  `resources/js/stores/` (Pinia), `resources/js/router/`, `resources/js/components/pages/`,
  `docs/architecture/`.

## Multi-tenancy

- Single database. A "dashboard" in the product is a `Tenant` in code.
- Scope tenant-owned models with the `BelongsToTenant` trait
  (`app/Models/Concerns/BelongsToTenant.php`).
- API tenant resolution (`ResolveTenantFromRequest`): bearer token (`tenant_id` on the
  personal access token), then domain mapping, then the default tenant.
- The Filament panel is tenant-aware (`->tenant(Tenant::class)`). It also accepts
  `?tenant=slug` / `X-Tenant` for authenticated users (`SetFilamentDefaultTenant`),
  gated by `canAccessTenant()`.
- Use the demo tenants (`default`, `demo-city`, `stadt-regensburg`) in examples and tests.

## Filament icons

- Use outline icons (`heroicon-o-*`) everywhere: navigation, table actions, form actions
  (Builder/Repeater delete) and custom actions. Never use solid (`heroicon-s-*`) or mini
  (`heroicon-m-*`).
- Global outline overrides live in `AdminPanelProvider::boot()`; theme switcher icons in
  `resources/views/vendor/filament-panels/components/theme-switcher/`.

## Localization

- Locales: `de` and `en`. Public URLs: `/:slug` (German), `/en/:slug` (English). The API
  takes `?locale=de|en`. Translatable model fields use Spatie `HasTranslations`.
- Never hardcode user-facing strings. Put labels, messages, navigation items and
  validation text in `resources/lang/{locale}/` and use `__()` or `trans()`.
- In Filament Resources and Pages, return `__('filament...')` from
  `getNavigationGroup()`, `getNavigationLabel()`, `getModelLabel()` etc. Do not use static
  `$navigationGroup` or `$navigationLabel` properties with literal text.

## Branches, commits and merging

Follow [CONTRIBUTING.md](CONTRIBUTING.md). In short:

- Branches: `feat/short-description`, `fix/short-description`, `chore/short-description`,
  optionally with a public issue number (`fix/123-short-description`).
- Commits follow Conventional Commits: `type(scope)?: description` with types
  `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`, `perf`. Mark breaking changes
  with `type!:` and a `BREAKING CHANGE:` footer. release-please derives versions and the
  changelog from them.
- PRs are squash-merged, so the PR title must follow the same format.
- Write code, comments, commits and PRs in English. Comments explain why, not what.
- Merge only when all tests and linters pass, no CodeRabbit or review thread is open, and
  the change contains no secrets or debug code (`dd()`, `console.log`).

## Documentation

- API docs (Scribe, Scalar theme) are served at `/docs`. Regenerate them with
  `ddev exec php artisan scribe:generate` (config `config/scribe.php`, output `public/docs/`).
- The CMS user guide in `docs/user-guide/` exists in German and English. When you change
  CMS behavior (Filament Resources, Pages, Fabricator blocks, settings, roles, navigation,
  branding), update both language versions. Follow `docs/user-guide/AGENTS.md`.

## Tech stack

Laravel 12 (PHP 8.2, Sanctum), Filament 3.3 with Filament Fabricator, Vue 3 with Vue
Router 4 and Pinia, Tailwind CSS 4, Vite 6, PostgreSQL 16, PHPUnit, Vitest, Playwright.
