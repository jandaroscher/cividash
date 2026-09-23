# Deployment documentation

CiviDash is a headless Laravel 12 + Vue 3 app. This directory covers everything needed
to run it: local development, production deployment, CI and a few operational topics.

## Which guide do I need

- [installation-standalone.md](installation-standalone.md): classic deployment on your own
  server or VM (composer install, npm build, webserver config, systemd/cron). No containers.
- [ddev.md](ddev.md): local development environment via DDEV. Not for production.
- [docker.md](docker.md): production deployment with Docker, based on the images and compose
  file under `docker/production/`.

## Related topics

- [seeding.md](seeding.md): database seed commands and when to use them.
- [timezone.md](timezone.md): `APP_TIMEZONE` handling and the one UTC exception.

## CI

CI runs as three separate workflows: `ci.yml` (frontend lint, unit tests, dependency audits,
build), `ci-postgres.yml` (Pint and the PHP test suite against PostgreSQL), and
`release-please.yml` (versioning and changelog), plus `pr-title-lint.yml` for PR titles. This
repository does not deploy anything. `dispatch-deploy.yml` only notifies an external
deployment pipeline after a push to `main`, if one is configured.

The Playwright E2E suite (`tests/e2e/`) has no CI workflow. The tenant-resolution,
data-isolation, api-key-lifecycle and admin-smoke specs pass on a freshly seeded database. The
default-tenant content specs expect demo tiles and pages on the default tenant, which no seeder
creates, so they fail there. Run it locally with `npm run
test:e2e`. This requires a running DDEV project, seeded fixtures (`npm run test:e2e:seed`), and
the configured tenant hostnames; see
[e2e-tenant-resolution.md](../testing/e2e-tenant-resolution.md) for setup.

## Database

The app supports PostgreSQL, MySQL/MariaDB and sqlite via `config/database.php`. CI
(`.github/workflows/ci-postgres.yml`) only tests PostgreSQL 16. Treat PostgreSQL as the primary, CI-verified target; MySQL/MariaDB and a
sqlite setup are supported by Laravel's driver but not exercised in this project's
CI. See the database section in installation-standalone.md for details.
