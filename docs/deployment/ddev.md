# Local development with DDEV

DDEV is the supported local development environment. It is not a production deployment method,
see docker.md or installation-standalone.md for that.

## Setup

```bash
ddev start                              # start the environment
ddev exec composer install              # install PHP dependencies
ddev exec npm install                   # install Node dependencies
ddev exec php artisan migrate           # run migrations
ddev exec php artisan tenancy:backfill  # create the default tenant
ddev exec php artisan db:seed --class=TenantSeeder  # seed demo data
```

Config: `.ddev/config.yaml`. Project type `laravel`, PHP 8.2, nginx-fpm, docroot `public`.

## Database: PostgreSQL 16

The DDEV database engine is PostgreSQL 16 (`database.type: postgres`, `version: "16"` in
`.ddev/config.yaml`), matching `config/database.php`'s `pgsql` connection. DDEV cannot
change the database engine of an existing project. On a database type mismatch, recreate
the project once:

```bash
ddev delete --omit-snapshot && ddev start
```

## Additional hostnames

`.ddev/config.yaml` configures `additional_hostnames` for multi-tenant local testing:
`a.open-source-dashboard`, `b.open-source-dashboard`, `demo-city.open-source-dashboard`,
`regensburg.open-source-dashboard`, alongside the default `open-source-dashboard.ddev.site`.
Useful for testing domain-based tenant resolution locally (see
`app/Http/Middleware/ResolveTenantFromRequest.php`).

## Working in a git worktree

Each git worktree needs its own DDEV project so it doesn't collide with the main checkout's
database and ports:

```bash
bash scripts/setup-worktree-ddev.sh          # suffix derived from the branch name
bash scripts/setup-worktree-ddev.sh my-branch # or pass an explicit suffix
```

The script rewrites `name` and `additional_hostnames` in the worktree's `.ddev/config.yaml` to
`open-source-dashboard-<suffix>`, so `ddev start` in that worktree creates a separate project
with its own database, then runs migrations, seeds and prints the resulting URL.

## Development server

Inside (or independent of) DDEV, `composer run dev` runs the Laravel server, queue worker,
log tailer and Vite dev server concurrently. Prefer this for day-to-day frontend work.

## Testing

```bash
ddev exec php artisan test                                    # full backend suite
ddev exec php artisan test --filter=ManageSiteSettingsTest     # single test class
ddev exec php artisan test tests/Feature/Api/                  # single directory
npx vitest run tests/js/components/Header.test.js              # single JS test file
npm run test:e2e                                                # Playwright E2E
```

Run the full suite only for final verification before committing; prefer filtered runs during
iteration.
