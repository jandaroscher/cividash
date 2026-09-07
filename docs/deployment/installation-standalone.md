# Standalone installation guide

This guide covers installing CiviDash on your own
server, outside of the DDEV/CI setup used for development. It reflects the current repo state
(composer.json, .env.example, `.ddev/config.yaml`, `.github/workflows/`). See "Known limitations" at the end for what this guide does not cover yet.

## 1. System requirements

- **PHP**: 8.2 or newer (composer.json `require.php` is `^8.2`). CI
- **PHP extensions**: `mbstring`, `intl`, `bcmath`, `gd`, `curl`, `xml`, `zip`, `redis`
  (from the CI `setup-php` step; see Known limitations on whether `redis` is strictly required).
- **Node.js**: 20 (`.github/workflows/ci.yml` `actions/setup-node`). `npm ci` is used,
  so a `package-lock.json` must be present.
- **Composer**: v2.
- **Database**: `config/database.php` defines four Laravel connections: `sqlite`, `mysql`,
  `mariadb` and `pgsql` (plus `sqlsrv`, unused elsewhere in this repo). **PostgreSQL 16** is the
  primary, CI-verified target (`.ddev/config.yaml` `database.type: postgres`, `version: "16"`;
  `.github/workflows/ci-postgres.yml` runs the backend test suite against `postgres:16`, plus
  a migration check). MySQL/MariaDB connections exist in `config/database.php` and are
  supported by Laravel, but no CI job in this repo runs against either, treat them as
  **supported, not CI-tested**. `.env.example` defaults `DB_CONNECTION` to `pgsql`, matching the
  CI-verified target; override the `DB_*` values with your own connection details.
- **Webserver**: nginx (DDEV uses `nginx-fpm`); Apache with `mod_php`/PHP-FPM also works since
  Laravel only needs `public/` as docroot. No repo-provided Apache/nginx vhost exists for a
  non-container install, the example below is derived from Laravel conventions. A container
  nginx config does exist for the Docker path, see docker.md.
- **Docroot**: `public/` (see `.ddev/config.yaml` `docroot: public`, and standard Laravel
  `resources/views/app.blade.php` SPA bootstrap per `AGENTS.md`).
- This guide covers a classic (non-container) install: composer/npm build on the target host,
  `docker/production/`, see docker.md if you'd rather deploy with Docker.

## 2. Step-by-step installation

```bash
# 1. Clone
git clone <repo-url> cividash && cd cividash

# 2. PHP dependencies (production: no dev packages)
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# 3. Frontend dependencies + build
npm ci
npm run build

# 4. Environment
cp .env.example .env
php artisan key:generate

# Edit .env: set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_* (see env table below)

# 5. Database migrations
php artisan migrate --force

# 6. Multi-tenancy bootstrap (creates/reuses the default tenant)
php artisan tenancy:backfill
# Custom slug: php artisan tenancy:backfill --default-tenant=my-slug

# 7. Storage symlink (required for uploaded media under storage/app/public)
php artisan storage:link

# 8. Create an admin user (required, there is no admin account without this step).
#    Option A: full seed (fastest, but ships demo/fixture data)
php artisan db:seed --force
#    DatabaseSeeder creates an admin User (via UserFactory, factory-default password),
#    then calls TenantSeeder (demo tenants) and RoleSeeder (roles/permissions).
#    Change the seeded admin password immediately after first login.
#
#    Option B: no demo data, seed only roles and create the admin explicitly
php artisan db:seed --class=RoleSeeder --force
php artisan cividash:create-admin admin@example.org --first-name=Ada --last-name=Admin
#    Do not use a plain `db:seed` here: DatabaseSeeder builds its users with model
#    factories, which need Faker, a dev dependency that `composer install --no-dev` skips.
#    `make:filament-user` does not fit this user model either (it writes a `name` column
#    and never sets is_admin).

# 9. Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Post-deploy steps for automated deployments

If you deploy a built artifact (for example, rsync from a CI job) instead of installing by hand,
run these steps on the server after every deployment, from the app root:

```bash
# Writable cache and storage directories
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 755 {} +
find storage bootstrap/cache -type f -exec chmod 644 {} +

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Generate APP_KEY only if .env has none yet; rotating it breaks sessions and encrypted data
grep -qE '^APP_KEY=.+' .env || php artisan key:generate --force

php artisan migrate --force
php artisan storage:link
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Build `public/build/` (`npm run build`) and `public/docs/` (`php artisan scribe:generate`, which
needs the dev dependencies) before creating the artifact, and ship both. Create the first admin once, as in step 8. Afterwards, check that `/up` returns HTTP 200.

## 3. Webserver configuration (nginx example)

```nginx
server {
    listen 80; # example only, terminate TLS in front of this or add a 443 server block before exposing login
    server_name example.com;
    root /path/to/cividash/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

No nginx/Apache vhost is committed in this repo (your hosting provider's managed hosting may
handle it). Adjust the PHP-FPM socket path and TLS termination for your environment.

## 4. Queue worker and scheduler

- **Queue**: `.env.example` sets `QUEUE_CONNECTION=database`, so the `jobs` table (created by
  Laravel's default migrations) is used. The app currently dispatches no queued jobs, so a worker
  is optional. Run a persistent one, e.g. via systemd or Supervisor, once you enable features
  that queue work:
  ```bash
  php artisan queue:work --sleep=3 --tries=3
  ```
  No queue worker step exists in `deploy/post_deploy.sh` or the GitHub Actions deploy job (see
  Open questions).
- **Scheduler**: `routes/console.php` defines two scheduled commands, both gated to
  `environments(['production'])`:
  - `dashboard:reset --force` daily at 03:00
  - `integration:sync-civitas` hourly or daily at 04:00 depending on
    `config('integrations.civitas.sync.schedule')`; the command itself checks
    `config('integrations.civitas.enabled')` and is a no-op (warns and exits) when
    `CIVITAS_ENABLED` is not `true`, so it's safe to leave scheduled even if the feature is unused.

  **Warning: `dashboard:reset` deletes data.** Its description in
  `app/Console/Commands/DashboardResetCommand.php` is explicit: *"Reset demo tenants: Default
  cleaned, Regensburg re-seeded, Demo City emptied."* This is a **demo-data reset command**, not a
  generic maintenance task. On a production instance seeded with real dashboards, running it
  nightly will delete/overwrite tenant content. Since the schedule entry only checks
  `environments(['production'])`, not whether the instance is an actual demo install, it fires
  on **any** install with `APP_ENV=production`.

  Before going live with real (non-demo) data, do one of:
  1. **Remove the schedule entry.** Delete the `Schedule::command('dashboard:reset --force')...`
     block from `routes/console.php`.
  2. **Gate it via env**, e.g.:
     ```php
     Schedule::command('dashboard:reset --force')
         ->daily()
         ->at('03:00')
         ->environments(['production'])
         ->when(fn () => config('app.demo_reset_enabled', false));
     ```
     `app.demo_reset_enabled` has no mapping in `config/app.php` yet; add one (e.g. reading a
     `DEMO_RESET_ENABLED` env var) before relying on this gate, and rebuild the config cache
     after changing it (`php artisan config:cache` does not pick up new `.env` values on its own).
     and only set the corresponding env flag on actual demo/showcase instances. See
     `docs/deployment/seeding.md` (Scheduler and Cron-Setup section) for how the schedule currently
     behaves.

  Add a single cron entry to run Laravel's scheduler every minute:
  ```cron
  * * * * * cd /path/to/cividash && php artisan schedule:run >> /dev/null 2>&1
  ```
  `APP_ENV=production` must be set for these scheduled tasks to fire, which is why the warning
  above matters.

## 5. Environment variables

Source: `.env.example`. "Required" = must have a real value for a working production install;
"Optional" = has a safe default or gates an optional feature.

| Variable | Required | Purpose |
|---|---|---|
| `APP_NAME` | Optional | Display name, used in mail "from name" etc. |
| `APP_ENV` | Required | Set to `production`. Also gates the scheduler entries above. |
| `APP_KEY` | Required | Generated via `php artisan key:generate`. |
| `APP_DEBUG` | Required | Set to `false` in production. |
| `APP_URL` | Required | Base URL, used for CORS origin derivation and health checks. |
| `APP_TIMEZONE` | Optional | Default `Europe/Berlin`. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Optional | Default `en`; app supports `de`/`en` per `AGENTS.md`. |
| `DB_CONNECTION` | Required | Defaults to `pgsql` in `.env.example` (see PostgreSQL note above). |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Required | PostgreSQL connection; override the `.env.example` placeholders (port **5432**) with your own values. |
| `SESSION_DRIVER` | Optional | Default `database`; requires `sessions` table (migration present). |
| `QUEUE_CONNECTION` | Optional | Default `database`; see Queue Worker section. |
| `CACHE_STORE` | Optional | Default `database`. |
| `FILESYSTEM_DISK` | Optional | Default `local`; set to `s3` with `AWS_*` vars if using S3-backed uploads. |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` | Optional | Only needed if `FILESYSTEM_DISK=s3` (package `league/flysystem-aws-s3-v3` is installed). |
| `MAIL_*` | Optional | Default `log` driver; set real SMTP creds for password reset / notification mail. |
| `FRONTEND_URL` | Optional | Extra allowed CORS origin (e.g. Vite dev server); subdomain origins are derived from `APP_URL` automatically. |
| `CIVITAS_ENABLED` | Optional | Enables CIVITAS/CORE data sync integration. |
| `CIVITAS_DRIVER` | Optional | `ngsi-ld` (Stellio, default) or `sensorthings` (FROST). |
| `CIVITAS_API_URL` | Optional | Broker endpoint, required if `CIVITAS_ENABLED=true`. |
| `CIVITAS_CONTEXT_URL` | Optional | JSON-LD `@context` URL override. |
| `CIVITAS_OAUTH_TOKEN_URL` / `CIVITAS_OAUTH_CLIENT_ID` / `CIVITAS_OAUTH_CLIENT_SECRET` / `CIVITAS_OAUTH_SCOPE` | Optional | OAuth client credentials for the CIVITAS broker. |
| `CIVITAS_SYNC_SCHEDULE` | Optional | `daily` (default) or `hourly`; drives the `integration:sync-civitas` cron cadence. |
| `CIVITAS_SYNC_PRUNE_REMOVED` | Optional | Prune local records absent from a full source sync. |
| `KEYCLOAK_SSO_ENABLED` | Optional | Enables Keycloak OIDC login for the Filament admin panel. |
| `KEYCLOAK_BASE_URL` | Required if SSO enabled | Keycloak server base URL (`config/services.php` `keycloak.base_url`). |
| `KEYCLOAK_REALM` | Optional | Defaults to `civitas` in `config/services.php`; set it if your realm is named differently. |
| `KEYCLOAK_CLIENT_ID` / `KEYCLOAK_CLIENT_SECRET` | Required if SSO enabled | OIDC client credentials. |
| `KEYCLOAK_REDIRECT_URI` | Optional | Default `/admin/auth/keycloak/callback`. |
| `KEYCLOAK_BASE_URL_INTERNAL` | Optional | Only needed in DDEV/Docker, for container-to-container calls. |
| `APP_COMMIT` | Optional | Not in `.env.example`. A deploy pipeline can write the deployed commit SHA here. No application code reads it; it only helps operators identify the running version. Not required for a manual standalone install. |

## 6. Update procedure

```bash
git pull
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

`queue:restart` signals the persistent queue worker to finish its current job and exit; the
process manager (systemd/Supervisor) must then start it again so it loads the deployed code.

This mirrors the post-deploy steps in section 2 minus the first-install-only `key:generate` step. Always back up the PostgreSQL database before running migrations on an
existing production install.

## 7. Multi-dashboard (multi-tenant) setup

"Dashboard" in the product sense maps to `Tenant` in code (see `app/Models/Tenant.php`).

- Create/register a new tenant via the Filament admin panel (`RegisterTenant` page at
  `app/Filament/Pages/Tenancy/RegisterTenant.php`), or directly in the `tenants` table.
- Tenant resolution priority for public API requests (see
  `app/Http/Middleware/ResolveTenantFromRequest.php`): Bearer token (`tenant_id` on
  `personal_access_tokens`) → `domain` column match on request host → tenant with slug
  `default`. Other contexts (Filament admin, session-based web requests) resolve tenants
  differently, with access checks, see `app/Models/Concerns/ResolvesCurrentTenant.php`.
- To map a domain to a tenant, set the `domain` column on the tenant record (and optionally
  `frontend_base_url`), added by migration
  `database/migrations/2026_01_14_121552_add_domain_columns_to_tenants.php`.
- `php artisan tenancy:backfill` creates/reuses the initial default tenant; run once on first
  install (`app/Console/Commands/TenancyBackfillCommand.php`, `--default-tenant=<slug>` option).

## 8. Keycloak OIDC (optional)

For SSO into the Filament admin panel via CIVITAS/CORE Keycloak (`socialiteproviders/keycloak`
package):

1. Set `KEYCLOAK_SSO_ENABLED=true`.
2. Set `KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`, `KEYCLOAK_CLIENT_ID`, `KEYCLOAK_CLIENT_SECRET`.
3. Adjust `KEYCLOAK_REDIRECT_URI` if the admin panel isn't served at `/admin`.
4. In Docker/DDEV setups where the app can't resolve the Keycloak host name normally, set
   `KEYCLOAK_BASE_URL_INTERNAL` to a container-reachable URL.

This repo has no automated test or doc confirming the Keycloak login flow end-to-end for a
standalone (non-DDEV) install. Validate it manually (see Known limitations).

## 9. Troubleshooting

- **Vite manifest not found**: Run `npm run build` on the server (or ship the built
  `public/build/` directory as part of your deployment artifact). Never rely on `npm run dev` in production.
- **Storage permission errors**: Ensure `storage/` and `bootstrap/cache/` are writable by the
  webserver user. The post-deploy steps in section 2 set directories to `755` and files to
  `644`; repeat them after every deployment.
- **Storage symlink missing / uploaded images 404**: Run `php artisan storage:link`.
- **`tenancy:backfill` reports tenant already exists**: safe, the command reuses an existing
  tenant matching the given slug rather than failing.
- **Health check fails after deploy**: verify `/up` (Laravel's built-in health check route)
  responds; check `storage/logs/laravel.log` and confirm `APP_KEY`, DB credentials, and
  `config:cache` succeeded (see the post-deploy steps in section 2).

## Known limitations

- The `redis` PHP extension is only needed when `REDIS_CLIENT=phpredis` is configured; the
  default setup does not use Redis.
- PostgreSQL 16 is the tested version. The code does not pin a minimum, and older versions are
  untested.
- The app does not require a persistent `queue:work` process. Run one if you enable features
  that dispatch queued jobs.
- The Keycloak OIDC login flow has no end-to-end test or guide for a standalone (non-DDEV)
  install.
