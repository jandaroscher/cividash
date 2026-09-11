# Docker deployment

CiviDash ships two production images and a smoke-test compose stack under
`docker/production/`. This guide documents that setup and how to run it.

## Images

Two images, built from the repo root as build context:

- `cividash-app` (`docker/production/Dockerfile`): multi-stage build. Stage 1 (`node:22-alpine`)
  builds the Vite frontend into `public/build`. Stage 2 (`composer:2`) installs production PHP
  dependencies. Stage 3 (`php:8.2-fpm-alpine`) is the runtime image: PHP-FPM on port 9000,
  running as `www-data`. This one image is the workload for `fpm`, `queue:work`, the
  `schedule:run` loop and one-shot `migrate --force`.
- `cividash-web` (`docker/production/nginx/Dockerfile`): thin rootless `nginxinc/nginx-unprivileged:alpine`
  front listening on port 8080 (no root, no capabilities). Copies
  `public/` from an already-built `cividash-app` image (`--build-arg APP_IMAGE=...`) and proxies
  PHP requests to `cividash-fpm:9000`. Config: `docker/production/nginx/nginx.conf`.

Build order matters, `cividash-web` reads `public/` from `cividash-app`:

```bash
docker build -f docker/production/Dockerfile -t cividash-app:dev .
docker build -f docker/production/nginx/Dockerfile -t cividash-web:dev .
```

## Entrypoint

`docker/production/entrypoint.sh` runs on every `cividash-app` container start (not at build time,
since `config:cache` must freeze the env injected by the orchestrator). It clears and rewarms
`config`, `route`, `view` and `filament:cache-components` caches, best-effort, then execs the
container command. It does not run migrations.

## Reference compose stack

`docker/production/docker-compose.smoke.yml` is a self-contained smoke stack (PostgreSQL +
migrate job + FPM + nginx) used to verify the images build and boot together. It uses a fixed,
non-secret `APP_KEY` and is meant to be thrown away, not run as-is in production. Run it from
the repo root:

```bash
docker compose -f docker/production/docker-compose.smoke.yml up --build
```

Then check `http://localhost:8088/up` (Laravel health check), `/` (SPA) and `/admin`
(Filament).

Services defined there:

- `cividash-db`: `postgres:16`, health-gated so dependants wait for readiness.
- `cividash-migrate`: one-shot `php artisan migrate --force`, exits after running.
- `cividash-fpm`: the app image, depends on `cividash-db` (healthy) and `cividash-migrate` (completed).
- `cividash-web`: nginx front on container port 8080, published on host port 8088.

A shared `public-media` volume carries `storage/app/public` between `cividash-fpm` and `cividash-web`
so uploads written by PHP are visible to nginx (local-disk equivalent of an S3 bucket).

## Adapting the smoke stack for standalone production use

The smoke compose file is explicitly not production config. For a real deployment, at minimum:

1. Generate a real `APP_KEY` (`php artisan key:generate --show`) instead of the hardcoded
   smoke key, and inject it as a secret rather than a plain compose environment value.
2. Set `APP_ENV=production`, a real `APP_URL`, and real `DB_*` credentials via your
   orchestrator's secret store.
3. Add a queue worker service if you need queued jobs processed (`QUEUE_CONNECTION=database`
   is the default; the smoke stack does not run a worker). Reuse the `cividash-app` image with
   `entrypoint: ["php", "artisan"]` and `command: ["queue:work", "--sleep=3", "--tries=3"]`.
4. Add a scheduler service similarly, with `command` running a loop that calls
   `php artisan schedule:run` once a minute (there is no packaged scheduler container in this
   repo; the smoke stack does not include one).
5. Mount or otherwise persist `storage/` beyond the `public-media` volume if you rely on local
   (non-S3) private storage.
6. Terminate TLS in front of `cividash-web`, or run a reverse proxy (see the nginx `resolver`
   comment in `nginx.conf` on why the FPM upstream is a re-resolved variable, relevant if you
   swap the FPM service name).
7. Create the first admin once the migrations have run, from inside a `cividash-app` container:
   `php artisan db:seed --class=RoleSeeder --force`, then
   `php artisan cividash:create-admin admin@example.org` (prompts for the password). The image
   has no dev dependencies, so a plain `db:seed` fails (its factories need Faker).
8. Read the `dashboard:reset` scheduler warning in installation-standalone.md before running any
   scheduler service against real (non-demo) data.

## Building images in CI

No workflow in this repository builds or publishes these images. `.github/workflows/ci-postgres.yml`
runs the backend test suite against PostgreSQL 16, without Docker images. Build the images
yourself as shown above. When you push `cividash-app` to a registry, pass its tag to the
`cividash-web` build via `--build-arg APP_IMAGE=...`; the `cividash-app:dev` default in the
Dockerfile is only for local builds.

## Production hardening

`.env.example` ships development-friendly defaults (`APP_DEBUG=true`, `LOG_LEVEL=debug`,
`SESSION_ENCRYPT=false`). Before running against real data, override:

- `APP_DEBUG=false` — never leak stack traces to end users.
- `LOG_LEVEL=info`, `LOG_CHANNEL=stderr` — quieter logs, and a container-friendly channel
  (`stderr` is captured by the orchestrator's log driver instead of writing to a file no one
  rotates).
- `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` — requires TLS in front of `cividash-web`.

TLS termination and HTTP security headers are otherwise the operator's responsibility. The
`nginx.conf` shipped in this repo sets a safe subset at server level (`X-Content-Type-Options:
nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`).
It deliberately does **not** set `Strict-Transport-Security` (HSTS) or a Content-Security-Policy,
since both are unsafe or need tuning without knowing the deployment's TLS setup and CSP needs
(Filament/Livewire, Fabricator blocks) — add those at your TLS-terminating reverse proxy/ingress.

## Limitations

- This repo has no packaged queue-worker or scheduler compose service. The entrypoint comment
  names a `schedule:run` loop and `queue:work` as valid workloads for the `cividash-app` image, but
  no concrete compose or Kubernetes definition exists for either. Treat the snippets above as a
  starting point, not a verified deployment.
- This repo has no production compose file, as opposed to the smoke stack, or Kubernetes
  manifests. If your deployment target is a specific orchestrator, adapt the smoke stack's
  service definitions rather than looking for a ready-made production file.
