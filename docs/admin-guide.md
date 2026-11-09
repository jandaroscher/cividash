# Admin guide

For operators who install, configure and run CiviDash. Editors who maintain content in
the admin panel read the [user guide](user-guide/en/00-introduction.md) instead
([German](user-guide/de/00-einstieg.md)).

## Install and deploy

- **CIVITAS/CORE (production path):** the
  [cividash-addon](https://github.com/jandaroscher/cividash-addon) repository. Its README
  covers inventory keys, required vaulted secrets, what the chart deploys and how to
  uninstall.
- **Docker without CORE:** [deployment/docker.md](deployment/docker.md) describes the two
  images, the entrypoint and how to adapt the smoke compose stack.
- **Server or VM without containers:**
  [deployment/installation-standalone.md](deployment/installation-standalone.md), including
  the first admin user, nginx, cron and Keycloak setup.
- Overview of all options: [deployment/README.md](deployment/README.md).

Services, ports and the important environment variables are listed in the
[README](../README.md#services-and-ports). For the HTTP headers your proxy should add,
read [deployment/security-headers.md](deployment/security-headers.md); for timestamps,
[deployment/timezone.md](deployment/timezone.md).

## Initial data

- Create the `default` tenant with `php artisan tenancy:backfill`, seed the roles with
  `php artisan db:seed --class=RoleSeeder --force`, then create the first admin with
  `php artisan cividash:create-admin admin@example.org`. `RoleSeeder` also makes the first
  user of every tenant a global admin, so run it on a fresh installation only or check the
  users first.
  ([standalone guide, step 8](deployment/installation-standalone.md#2-step-by-step-installation)).
  On CORE, admins can sign in through Keycloak when `KEYCLOAK_SSO_ENABLED` is set; the first
  admin's role is granted in Keycloak by hand, see the add-on's `docs/authentication.md`.
- Seed pages, navigation or dashboard content: [deployment/seeding.md](deployment/seeding.md).
- Import indicator data as JSON bundles: [user-guide/datenimport.en.md](user-guide/datenimport.en.md),
  formats in [upload/README.md](upload/README.md).
- Additional dashboards (tenants) with their own domain:
  [installation-standalone.md, section 7](deployment/installation-standalone.md#7-multi-dashboard-multi-tenant-setup).

## Scheduled jobs

The scheduler runs `php artisan schedule:run` every minute (cron on a server, the
`cividash-scheduler` deployment on CORE). `routes/console.php` registers:

| Command | When | Note |
|---------|------|------|
| `integration:sync-civitas` | daily 04:00, or hourly with `CIVITAS_SYNC_SCHEDULE=hourly` | Only with `APP_ENV=production`; does nothing unless `CIVITAS_ENABLED=true` |
| `sanctum:prune-expired --hours=24` | daily 03:30 | Deletes expired API tokens |
| `auth:clear-resets` | daily 03:35 | Deletes expired password reset tokens |
| `queue:prune-failed --hours=2160` | daily 03:40 | Deletes failed jobs older than 90 days |
| `dashboard:reset --force` | daily 03:00 | Only registered with `DASHBOARD_DEMO_RESET=true` and `APP_ENV=production`. **Deletes content**; for demo instances only |

`php artisan schedule:list` shows what is registered on a running instance. The queue
worker (`queue:work`) can run, but the application dispatches no queued jobs today.

## Logging

In containers, set `LOG_CHANNEL=stderr` so the platform collects the logs; the add-on
does this by default. On a server the default `stack` channel writes to
`storage/logs/laravel.log`. Use `LOG_LEVEL=warning` or `info` in production. The nginx
access log of `cividash-web` contains no client IP addresses; see the privacy section of
the [README](../README.md#privacy).

## Backups

CiviDash does not back itself up. Back up:

- the **PostgreSQL database**: it holds all tenants, users, content, settings and the
  import history. On CORE, backups are the job of whichever Postgres hosts the database.
- the **media**: the S3 bucket (`PUBLIC_S3_BUCKET` or `AWS_BUCKET`), or the volume behind
  `storage/app/public` with the local driver.
- the **`APP_KEY`**: changing it invalidates sessions and encrypted data. On CORE it lives
  in the `cividash-app-secret` Secret, which survives `helm uninstall`.

Back up the database before every update, since migrations run automatically.

## Updates and release pinning

Releases are tagged `vX.Y.Z` on GitHub ([CHANGELOG.md](../CHANGELOG.md)). Each release
publishes `cividash-app` and `cividash-web` with the tags `X.Y.Z`, `X.Y` and `latest`.

- Pin both images to the same full `X.Y.Z` tag; never run a moving tag in production. The
  add-on pins the tag in its `vars/software_references.yml`, and an inventory overrides
  it per image through `inv_addons.cividash.images.{app,web}.tag` (an inventory value for
  `software.addon_cividash.*` has no effect).
- Migrations run before the new version starts: the `cividash-migrate` Helm hook on CORE,
  the one-shot migrate service with Compose, or `php artisan migrate --force` in the
  [standalone update procedure](deployment/installation-standalone.md#6-update-procedure).
- After a configuration change, recreate the app, queue and scheduler containers (with
  Compose `docker compose up -d`; `restart` keeps the old environment): the entrypoint
  caches the configuration at start.

Watch the GitHub repository for releases and security fixes. Report vulnerabilities as
described in [SECURITY.md](../SECURITY.md).
