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

test:e2e`.

## Database

The app supports PostgreSQL, MySQL/MariaDB and sqlite via `config/database.php`. CI
(`.github/workflows/ci-postgres.yml`) only tests PostgreSQL 16. Treat PostgreSQL as the primary, CI-verified target; MySQL/MariaDB and a
sqlite setup are supported by Laravel's driver but not exercised in this project's
CI. See the database section in installation-standalone.md for details.
