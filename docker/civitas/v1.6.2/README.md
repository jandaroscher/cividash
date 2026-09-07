# Local Stellio NGSI-LD broker (CORE V1.6.2-aligned)

A local-only NGSI-LD context broker for validating the dashboard's CIVITAS/CORE Pull pipeline end to end against a real, ETSI NGSI-LD-compliant broker, without needing access to the auth-gated CORE GitLab.

We run Stellio 2.21.0 (the latest stable release, 2025-02-26). Stellio is the same FIWARE Generic Enabler that sits behind APISIX in CORE production, so a bare local Stellio exposes the exact NGSI-LD API our `NgsiLdClient` and `NgsiLdDataMapper` consume.

> This stack is LOCAL ONLY. It is a separate `docker compose` project from the V2 reference stack in the parent directory (`docker/civitas/`), which stays untouched as a SensorThings/FROST reference.

## What's in the stack

| Service | Image | Role |
|---------|-------|------|
| `api-gateway` | `stellio/stellio-api-gateway:2.21.0` | NGSI-LD entry point (`:8080` internal) |
| `search-service` | `stellio/stellio-search-service:2.21.0` | NGSI-LD entity store (GET/POST `/entities`) |
| `subscription-service` | `stellio/stellio-subscription-service:2.21.0` | Canonical set; not needed for Pull |
| `postgres` | `stellio/stellio-timescale-postgis:16-2.17.2-3.5` | Entity store backend (PG16 + Timescale + PostGIS) |
| `kafka` | `confluentinc/cp-kafka:7.6.0` | Event bus (KRaft mode, no Zookeeper) |

Authentication is disabled (`STELLIO_AUTHENTICATION_ENABLED=false`): the broker is fully open, so no JWT/OAuth is needed. Our `NgsiLdClient` simply skips token acquisition when `CIVITAS_OAUTH_*` is empty.

## Prerequisites

- Docker + Docker Compose v2
- `jq` and `curl` (used by `setup.sh` for seeding + readiness checks)

## Quick start

```bash
# Bring up the broker and seed the 3 indicators (bounded, ~5 min max).
./docker/civitas/v1.6.2/setup.sh

# Tear down and wipe data.
./docker/civitas/v1.6.2/teardown.sh
```

`setup.sh` auto-creates a local `.env` from `.env.example` on first run. To pin different image tags or change the host port, copy and edit it yourself:

```bash
cp docker/civitas/v1.6.2/.env.example docker/civitas/v1.6.2/.env
```

## Ports and URLs

The api-gateway (`:8080` internally) is published to the host on `8090` (`API_GATEWAY_HOST_PORT` in `.env`).

| Context | NGSI-LD base URL |
|---------|------------------|
| Host (browser / curl) | `http://localhost:8090/ngsi-ld/v1` |
| From inside DDEV | `http://host.docker.internal:8090/ngsi-ld/v1` |

### DDEV networking note

The Laravel/Filament app runs inside DDEV, while this broker runs in a separate docker compose on the host. DDEV containers reach the host via the `host.docker.internal` alias. Because the api-gateway port is published to the host, DDEV can reach it at `http://host.docker.internal:8090/ngsi-ld/v1`. (`localhost` from inside DDEV would point at the DDEV container itself, not the broker, so always use `host.docker.internal`.)

## `/ngsi-ld/v1` (local) vs `/context/ngsi-ld` (production)

Bare Stellio serves NGSI-LD at its native base path `/ngsi-ld/v1`. CORE production fronts Stellio with APISIX and exposes NGSI-LD at `/context/ngsi-ld`.

Our config default (`config/integrations.php`) stays on the production path `/context/ngsi-ld`. For local dev we override it via the `CIVITAS_API_URL` env var to Stellio's native `/ngsi-ld/v1`. The config itself never changes for this.

## Connecting the dashboard (sync proof)

Set these in the Laravel app's root `.env` (gitignored, never commit):

```dotenv
CIVITAS_ENABLED=true
CIVITAS_DRIVER=ngsi-ld
CIVITAS_API_URL=http://host.docker.internal:8090/ngsi-ld/v1
# Leave CIVITAS_OAUTH_* unset/empty; auth is disabled, no token is acquired.
```

Then run the sync from DDEV:

```bash
ddev exec php artisan integration:sync-civitas --tenant=<slug> --dry-run   # preview
ddev exec php artisan integration:sync-civitas --tenant=<slug>             # persist
```

## Seed data

`seed-ngsi-ld.json` contains the 3 indicators from the V2 `seed-data.json`, re-shaped as NGSI-LD `NachhaltigkeitsIndikator` entities exactly as our `NgsiLdDataMapper` expects. `id` follows `urn:ngsi-ld:NachhaltigkeitsIndikator:<slug>`. `name` and `description` are a `LanguageProperty` with a `de`/`en` `languageMap`. `unit` is a `Property` holding a scalar string. `category` is a `Relationship` to `urn:ngsi-ld:Category:<key>` (`energie` / `mobilitaet` / `klima`; the mapper slugifies the last URN segment into the category key). `dataPoints` is a `Property` whose `value` is `[{ "year": int, "value": float }]`, deliberately not `values`: under the NGSI-LD core context, `values` expands to the reserved term `hasValues`, which Stellio, and thus production CORE, rejects with 400. Each entity also carries a top-level `@context` (NGSI-LD core context).

| Indicator | Category | Unit | Years (value) |
|-----------|----------|------|---------------|
| Erneuerbare Energien | energie | MW | 2015 (26.1), 2018 (35.4), 2020 (44.2), 2022 (55.8), 2023 (64.0) |
| Radverkehr | mobilitaet | % | 2015 (18.5), 2018 (20.1), 2020 (21.8), 2022 (23.4) |
| CO2-Emissionen | klima | t CO2/a | 2015 (8.2), 2018 (7.5), 2020 (6.8), 2022 (6.1) |

`setup.sh` posts each entity to `POST /ngsi-ld/v1/entities` with `Content-Type: application/ld+json` and treats HTTP 409 (already exists) as success, so it's safe to re-run.

## Bounded bring-up

`setup.sh` never spins indefinitely: `docker compose up -d --wait` is capped at 180s, the NGSI-LD readiness poll adds at most ~120s, and on timeout the script dumps `docker compose logs --tail=80` and exits non-zero (reports a blocker) rather than looping.

## Upgrading Stellio

Bump `STELLIO_DOCKER_TAG` in `.env` and keep the dependency image tags (`POSTGRES_IMAGE_TAG`, `KAFKA_IMAGE_TAG`) coherent with that release's official `docker-compose.yml`. Avoid the `latest-dev` tag, a moving target on `develop`.

## Postgres data persistence

The `stellio-timescale-postgis` image stores data at `/var/lib/postgresql/16/main` (Kartoza/Debian layout), not `/var/lib/postgresql/data`. The volume must mount the former, or postgres falls back to the image's baked data dir and loses all data on every container recreate. The compose file mounts `.../16/main`.

## Hosted demo deployment

The hosted demo deployment is maintained separately and is not part of this repository.
