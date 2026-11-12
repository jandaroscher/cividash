<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="public/images/branding/cividash-logo-dark.svg">
    <img src="public/images/branding/cividash-logo.svg" alt="CiviDash" width="320">
  </picture>
</h1>

CiviDash is an open-source sustainability dashboard for municipalities, built on
Laravel (backend/admin via Filament) and Vue 3 (frontend). It supports multi-tenancy
(multiple dashboards/tenants) and multilingual content (German/English), with KPI
tiles, filtering, full-text search, a Filament-based page builder and data import. The
related CIVITAS/CORE add-on is maintained separately.

- Repository: https://github.com/jandaroscher/cividash (mirror on openCode: https://gitlab.opencode.de/regensburg_next/cividash)
- Issues: https://github.com/jandaroscher/cividash/issues
- CIVITAS/CORE add-on: https://github.com/jandaroscher/cividash-addon
- Production deployment: [CIVITAS/CORE add-on](#production-deployment), other setups in [docs/deployment/](docs/deployment/README.md)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
- Security policy: [SECURITY.md](SECURITY.md)

## Where to start

- **Editors and admins in the municipal administration:** the admin panel of a running
  dashboard is at `/admin` on its domain, e.g. `https://dashboard.example.org/admin`. Sign
  in with email and password, or with **Sign in with Keycloak** where single sign-on is set
  up. Accounts are created by an admin of the dashboard. The
  [user guide](docs/user-guide/en/00-introduction.md)
  ([German](docs/user-guide/de/00-einstieg.md)) explains the panel step by step, starting
  with [login and interface](docs/user-guide/en/01-getting-started.md)
  ([German](docs/user-guide/de/01-erste-schritte.md)).
- **Operators who install and run CiviDash:** start with the
  [admin guide](docs/admin-guide.md). It covers deployment and how to create the first
  admin account.
- **Developers:** see [Quickstart](#quickstart) and the
  [developer guide](docs/developer-guide.md).

## Features

- KPI tiles with time series
- Filtering by category groups
- Full-text search
- Multilingual content (German/English)
- Multi-dashboard / multi-tenancy
- Filament admin with page builder
- Data import (JSON bundles) and export (JSON/CSV)
- CIVITAS/CORE integration

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2, Sanctum |
| Admin | Filament 3.3, Filament Fabricator |
| Frontend | Vue 3, Vue Router 4, Pinia |
| Styling | Tailwind CSS 4 |
| Build | Vite 6 |
| Database | PostgreSQL 16 |
| Testing | PHPUnit, Vitest, Playwright |

## Quickstart

Local development runs through [DDEV](https://ddev.com/). Node.js 22 or newer is
required outside of DDEV (e.g. for editor tooling); see `.nvmrc` (`nvm use`).

```bash
ddev start                              # Start environment
ddev exec composer install              # Install PHP dependencies
ddev exec php artisan key:generate      # Generate app key (DDEV creates .env itself)
ddev exec npm install                   # Install Node dependencies
ddev exec php artisan migrate           # Run migrations
ddev exec php artisan tenancy:backfill  # Setup multi-tenancy (creates default tenant)
ddev exec php artisan db:seed           # Seed demo data (tenants, roles)
ddev exec npm run build                 # Build frontend assets (public routes and /admin need the Vite manifest)
```

DDEV cannot change the database engine of an existing project. On a database type
mismatch, recreate the project once:

```bash
ddev delete --omit-snapshot && ddev start
```

## Production deployment

The production path is the **CIVITAS/CORE add-on**: the
[cividash-addon](https://github.com/jandaroscher/cividash-addon) repository holds a Helm
chart that the CORE platform deployment (Ansible) installs, together with the Keycloak
client and the APISIX route. It runs the release images
`ghcr.io/jandaroscher/cividash-app` and `ghcr.io/jandaroscher/cividash-web`, pinned to a
release tag `X.Y.Z` (pin the image digest if you need byte-identical images). How CiviDash fits into CORE is described in
[docs/architecture/civitas-core/architecture.md](docs/architecture/civitas-core/architecture.md).

Other setups:

- Docker Compose or a plain server/VM without CORE: see
  [docs/deployment/](docs/deployment/README.md) ([docker.md](docs/deployment/docker.md),
  [installation-standalone.md](docs/deployment/installation-standalone.md)).
- DDEV is for local development only ([docs/deployment/ddev.md](docs/deployment/ddev.md)).
- Local CIVITAS/CORE test environment: [docker/civitas/README.md](docker/civitas/README.md).

Operators start with the [admin guide](docs/admin-guide.md). Hosted deployments are
maintained outside this repository.

## Services and ports

| Service | What runs | Port | Configured by |
|---------|-----------|------|---------------|
| `cividash-web` | nginx (rootless) from the `cividash-web` image, serves `public/` and passes PHP to `cividash-fpm` | 8080 in the container; the add-on's Kubernetes Service maps 80 to 8080 | `docker/production/nginx/nginx.conf` |
| `cividash-fpm` | PHP-FPM from the `cividash-app` image (Laravel, Filament, API) | 9000 (FastCGI) | `docker/production/php/www.conf` |
| `cividash-queue` (add-on) | `php artisan queue:work`, same app image | none | `QUEUE_CONNECTION` (default `database`) |
| `cividash-scheduler` (add-on) | `php artisan schedule:run` every 60 seconds, same app image | none | `routes/console.php` |
| `cividash-migrate` | one-shot `php artisan migrate --force` before each rollout | none | `DB_*` |
| `cividash-seed` (add-on) | one-shot seed job after each install and upgrade: creates the default tenant if missing, sets its domain and, if enabled, updates seeded pages | none | add-on `docs/seed.md` |
| PostgreSQL 16 | application database (all tenants in one database; sessions, cache and queue tables) | 5432 | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| S3-compatible storage | public media (uploads) when `PUBLIC_DISK_DRIVER=s3`; otherwise a shared volume on `storage/app/public` | as in `AWS_ENDPOINT` | `PUBLIC_DISK_DRIVER`, `AWS_*`, `PUBLIC_S3_BUCKET`, `FILESYSTEM_PUBLIC_URL` |
| Keycloak (optional) | OpenID Connect login for the admin panel | as in `KEYCLOAK_BASE_URL` | `KEYCLOAK_*` |
| CIVITAS/CORE broker (optional) | NGSI-LD source for the scheduled data sync | as in `CIVITAS_API_URL` | `CIVITAS_*` |

The Docker Compose smoke stack (`docker/production/docker-compose.smoke.yml`) publishes
`cividash-web` on host port 8088. The health endpoint is `/up`.

## Configuration

All deployment settings come from environment variables, read through `config/*.php`;
per-tenant panel settings such as branding are stored in the database. The entrypoint
of the app image runs `config:cache` at container start, so a changed variable needs a
restart. [.env.production.example](.env.production.example) lists the production
variables with secret/non-secret markers, and
[installation-standalone.md](docs/deployment/installation-standalone.md#5-environment-variables)
explains more of them.
Defaults below are the code defaults from `config/`.

| Group | Key | Default | Purpose |
|-------|-----|---------|---------|
| App | `APP_KEY` | none | Encryption key, secret. Keep it stable; changing it invalidates sessions and encrypted data |
| App | `APP_ENV` / `APP_DEBUG` | `production` / `false` | `integration:sync-civitas` and `dashboard:reset` only run on schedule with `APP_ENV=production` |
| App | `APP_URL` | `http://localhost` | Public base URL; CORS origins are derived from its host |
| App | `APP_LOCALE` / `APP_TIMEZONE` | `en` / `Europe/Berlin` | Default locale (`de` or `en`) and timezone |
| Database | `DB_CONNECTION` | `sqlite` | Set to `pgsql`; PostgreSQL 16 is the only target tested in CI |
| Database | `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `127.0.0.1` / `5432` / `laravel` / `root` / empty | PostgreSQL connection; the password is secret |
| State | `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | Shared state in the database, no Redis needed |
| State | `SESSION_SECURE_COOKIE` | unset | Set to `true` behind TLS |
| Media | `PUBLIC_DISK_DRIVER` | `local` | `s3` stores public media in object storage |
| Media | `FILESYSTEM_PUBLIC_URL` | `/storage` | Public base URL of media, see [Media on S3](#media-on-s3) |
| Media | `AWS_ENDPOINT` / `PUBLIC_S3_BUCKET` / `AWS_USE_PATH_STYLE_ENDPOINT` | empty / `AWS_BUCKET` / `false` | S3-compatible endpoint, bucket, path-style URLs (`true` for MinIO/RustFS). The bucket falls back to `AWS_BUCKET` only if `PUBLIC_S3_BUCKET` is unset, not if it is empty as in `.env.production.example` |
| Media | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | empty | S3 credentials, secret |
| Proxy | `TRUSTED_PROXIES` | `*` | Proxies whose `X-Forwarded-*` headers are trusted |
| Logging | `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `debug` | Use `stderr` and `warning` or `info` in containers |
| CORE sync | `CIVITAS_ENABLED` | `false` | Enables the scheduled `integration:sync-civitas` pull |
| CORE sync | `CIVITAS_API_URL` / `CIVITAS_DRIVER` | `http://localhost:8080/context/ngsi-ld` / `ngsi-ld` | Broker URL and driver (`ngsi-ld` or `sensorthings`) |
| CORE sync | `CIVITAS_OAUTH_TOKEN_URL` / `CIVITAS_OAUTH_CLIENT_ID` / `CIVITAS_OAUTH_CLIENT_SECRET` | empty | OAuth2 client credentials for the broker; the secret is secret |
| CORE sync | `CIVITAS_SYNC_SCHEDULE` | `daily` | `daily` (04:00) or `hourly` |
| SSO | `KEYCLOAK_SSO_ENABLED` / `KEYCLOAK_SSO_PASSWORD_LOGIN` | `false` / `true` | Keycloak login for the admin panel; `false` disables password login while SSO is on |
| SSO | `KEYCLOAK_BASE_URL` / `KEYCLOAK_REALM` / `KEYCLOAK_CLIENT_ID` / `KEYCLOAK_CLIENT_SECRET` | empty / `civitas` / empty / empty | OIDC client; the secret is secret |
| Demo | `DASHBOARD_DEMO_RESET` | `false` | `true` deletes all content of the `default` tenant and reseeds Demo City nightly. Never enable on real data |

### Media on S3

With `PUBLIC_DISK_DRIVER=s3` the app stores uploads at the root of `PUBLIC_S3_BUCKET` and
answers `/storage/<path>` itself by reading the object with its own credentials. The
bucket can stay private, but every object in it can be fetched by its key, so use a bucket
for public media only (not the `AWS_BUCKET` of `FILESYSTEM_DISK=s3`). `FILESYSTEM_PUBLIC_URL` decides where browsers load media from:

- empty or `/storage`: from the app, on whichever domain the dashboard is opened. Use this
  when tenants have their own domains. Share images (`og:image`) are then relative URLs.
- `https://<app host>/storage`: from the app on one fixed host, with absolute share images.
- the bucket URL, e.g. `https://s3.example.org/cividash`: straight from the bucket. This
  needs anonymous read (`s3:GetObject`) on the bucket and the bucket origin in the CSP.

Up to 1.1.2 the s3 driver prefixed every object key with the container's storage path
(`/var/www/html/storage/app/public/`). After upgrading, move existing objects to the
bucket root (strip that prefix) or upload them again, and re-import theme bundles, which
store absolute media URLs.

## Documentation

- [Admin guide](docs/admin-guide.md) and [developer guide](docs/developer-guide.md)
- [Architecture overview](docs/architecture.md)
- [API documentation](docs/api) and the Scribe reference at `/docs`, once built with `php artisan scribe:generate` (not included in the release images)
- [Deployment](docs/deployment)
- [Testing](docs/testing)
- [Data upload](docs/upload)
- [User guide](docs/user-guide) (CMS usage, German and English)
- [CHANGELOG.md](CHANGELOG.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Where maintenance happens

GitHub is the working and release repository. Development, issues and pull requests,
dependency updates (Dependabot), releases (tags, GitHub Releases and
[CHANGELOG.md](CHANGELOG.md), created by release-please) and the production images
`ghcr.io/jandaroscher/cividash-app` and `ghcr.io/jandaroscher/cividash-web` all come from
https://github.com/jandaroscher/cividash. Watch that repository for new releases and
security fixes. The openCode repository is a mirror for discoverability; changes made
there are not merged back.

## Privacy

CiviDash ships with **no tracking out of the box**: no web analytics, no tag manager,
no social media plugins, no video embeds and no fonts or avatars loaded from third-party
servers (the bundled fonts are served locally; `tests/Feature/Privacy/NoThirdPartyHostsTest.php`
guards this). The public frontend and tile embeds set no cookies and start no server-side
session, so nothing about anonymous visitors (IP address, user agent) is stored in the
database (`tests/Feature/Privacy/AnonymousVisitorSessionTest.php`). Only the admin panel
uses a session cookie. The frontend uses `localStorage` for functional state (language,
help hints), so no consent banner is required. The `cividash-web` access log records time,
method, path without query string, status, size and duration, but no IP address, user
agent or referer, and `cividash-fpm` writes no access log. Error logs of nginx still
contain the client address of the failed request. Adding tracking is an operator decision and
requires a code change; it cannot be switched on by configuration.

## Contact and security

- Security vulnerabilities: report them privately as described in [SECURITY.md](SECURITY.md)
  (machine-readable: [public/.well-known/security.txt](public/.well-known/security.txt)).
  Do not open a public issue.
- Bugs, questions and feature requests: [GitHub issues](https://github.com/jandaroscher/cividash/issues).

## License

Licensed under the EUPL-1.2 or (at your option) any later version (SPDX: `EUPL-1.2+`). See [LICENSE](LICENSE) for the full text and [NOTICE](NOTICE) for bundled third-party components. From v1.1.0 on, every [release](https://github.com/jandaroscher/cividash/releases) carries SPDX SBOMs: one for the locked production dependencies from Composer and npm including all transitive packages (development tooling is left out), and one for each container image.
