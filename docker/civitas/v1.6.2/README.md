# Local Stellio NGSI-LD Broker (CORE V1.6.2-aligned)

A **local-only** NGSI-LD context broker for validating the dashboard's
CIVITAS/CORE **Pull pipeline** end to end against a real, ETSI NGSI-LD-compliant
broker — without needing access to the auth-gated CORE GitLab.

We run **Stellio 2.21.0** (the latest stable release, 2025-02-26). Stellio is
the same FIWARE Generic Enabler that sits behind APISIX in CORE production, so a
bare local Stellio exposes the exact NGSI-LD API surface our `NgsiLdClient` and
`NgsiLdDataMapper` consume.

> This stack is **LOCAL ONLY**. It is a separate `docker compose` project from
> the V2 reference stack in the parent directory (`docker/civitas/`), which is
> kept untouched as a SensorThings/FROST reference.

## What's in the stack

| Service | Image | Role |
|---------|-------|------|
| `api-gateway` | `stellio/stellio-api-gateway:2.21.0` | NGSI-LD entry point (`:8080` internal) |
| `search-service` | `stellio/stellio-search-service:2.21.0` | NGSI-LD entity store (GET/POST `/entities`) |
| `subscription-service` | `stellio/stellio-subscription-service:2.21.0` | Canonical set; not needed for Pull |
| `postgres` | `stellio/stellio-timescale-postgis:16-2.17.2-3.5` | Entity store backend (PG16 + Timescale + PostGIS) |
| `kafka` | `confluentinc/cp-kafka:7.6.0` | Event bus (KRaft mode, no Zookeeper) |

**Authentication is DISABLED** (`STELLIO_AUTHENTICATION_ENABLED=false`): the
broker is fully open, so no JWT/OAuth is needed. Our `NgsiLdClient` simply skips
token acquisition when `CIVITAS_OAUTH_*` is empty.

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

`setup.sh` auto-creates a local `.env` from `.env.example` on first run. To pin
different image tags or change the host port, copy and edit it yourself:

```bash
cp docker/civitas/v1.6.2/.env.example docker/civitas/v1.6.2/.env
```

## Ports & URLs

The api-gateway (`:8080` internally) is published to the host on
**`8090`** (`API_GATEWAY_HOST_PORT` in `.env`).

| Context | NGSI-LD base URL |
|---------|------------------|
| Host (browser / curl) | `http://localhost:8090/ngsi-ld/v1` |
| **From inside DDEV** | `http://host.docker.internal:8090/ngsi-ld/v1` |

### DDEV networking note

The Laravel/Filament app runs **inside DDEV**, while this broker runs in a
**separate docker compose on the host**. DDEV containers reach the host via the
`host.docker.internal` alias. Because the api-gateway port is **published to the
host**, DDEV can reach it at `http://host.docker.internal:8090/ngsi-ld/v1`.
(`localhost` from inside DDEV would point at the DDEV container itself, not the
broker — always use `host.docker.internal`.)

## `/ngsi-ld/v1` (local) vs `/context/ngsi-ld` (production)

- **Bare Stellio** serves NGSI-LD at its **native** base path `/ngsi-ld/v1`.
- **CORE production** fronts Stellio with APISIX and exposes NGSI-LD at
  `/context/ngsi-ld`.

Our config default (`config/integrations.php`) stays on the **production** path
`/context/ngsi-ld`. For LOCAL dev we **override** it via the `CIVITAS_API_URL`
env var to Stellio's native `/ngsi-ld/v1`. The config is never changed for this.

## Connecting the dashboard (sync proof)

Set these in the Laravel app's **root** `.env` (gitignored — never commit):

```dotenv
CIVITAS_ENABLED=true
CIVITAS_DRIVER=ngsi-ld
CIVITAS_API_URL=http://host.docker.internal:8090/ngsi-ld/v1
# Leave CIVITAS_OAUTH_* unset/empty — auth is disabled, no token is acquired.
```

Then run the sync from DDEV:

```bash
ddev exec php artisan integration:sync-civitas --tenant=<slug> --dry-run   # preview
ddev exec php artisan integration:sync-civitas --tenant=<slug>             # persist
```

## Seed data

`seed-ngsi-ld.json` contains the 3 indicators from the V2 `seed-data.json`,
re-shaped as NGSI-LD `NachhaltigkeitsIndikator` entities exactly as our
`NgsiLdDataMapper` expects:

- `id` — `urn:ngsi-ld:NachhaltigkeitsIndikator:<slug>`
- `name` / `description` — `LanguageProperty` with a `de`/`en` `languageMap`
- `unit` — `Property` (scalar string)
- `category` — `Relationship` to `urn:ngsi-ld:Category:<key>`
  (`energie` / `mobilitaet` / `klima`; the mapper slugifies the last URN segment
  into the category key)
- `dataPoints` — `Property` whose `value` is `[{ "year": int, "value": float }]`
  (NOT `values`: under the NGSI-LD core context `values` expands to the reserved
  term `hasValues`, which Stellio — and thus production CORE — rejects with 400)
- each entity carries a top-level `@context` (NGSI-LD core context)

| Indicator | Category | Unit | Years (value) |
|-----------|----------|------|---------------|
| Erneuerbare Energien | energie | MW | 2015 (26.1), 2018 (35.4), 2020 (44.2), 2022 (55.8), 2023 (64.0) |
| Radverkehr | mobilitaet | % | 2015 (18.5), 2018 (20.1), 2020 (21.8), 2022 (23.4) |
| CO2-Emissionen | klima | t CO2/a | 2015 (8.2), 2018 (7.5), 2020 (6.8), 2022 (6.1) |

`setup.sh` POSTs each entity to `POST /ngsi-ld/v1/entities` with
`Content-Type: application/ld+json` and treats HTTP 409 (already exists) as
success, so it is safe to re-run.

## Bounded bring-up

`setup.sh` never spins indefinitely: `docker compose up -d --wait` is capped at
180s, the NGSI-LD readiness poll adds at most ~120s, and on timeout the script
dumps `docker compose logs --tail=80` and exits non-zero (reports a blocker)
rather than looping.

## Upgrading Stellio

Bump `STELLIO_DOCKER_TAG` in `.env` and keep the dependency image tags
(`POSTGRES_IMAGE_TAG`, `KAFKA_IMAGE_TAG`) coherent with that release's official
`docker-compose.yml`. Avoid the `latest-dev` tag (a moving target on `develop`).

## Postgres data persistence

`docker-compose.hosted.yml` is the variant deployed to the hosting project
**`p-example`** (default stack) as the shared CORE-V1.6.2 stage broker, alongside
the staging dashboard. Same Stellio topology, adapted for the hosting provider.

## Hosted demo deployment

Three hosting specifics (already baked into the compose / learned the hard way):

1. **No `-` in env-var KEYS** (API regex `[a-zA-Z_][a-zA-Z0-9_.]*`). Spring keys
   use the relaxed-binding form without dashes:
   `SPRING_KAFKA_BOOTSTRAPSERVERS` → `spring.kafka.bootstrap-servers`.
2. **Disable TimescaleDB auto-tuning** (`ACCEPT_TIMESCALE_TUNING=FALSE`). The
   image otherwise tunes `postgresql.conf` for the **host node's** RAM (~755 GB on
   the cluster node), which blows past the container memory limit and crash-loops
   postgres. Disabled → conservative defaults that fit in the 1.5g limit.
3. **`mw stack deploy` updates the stored spec but does NOT restart running
   containers** to match env/volume changes. To apply a changed service: either
   `mw container rm <id>` then redeploy (recreates it fresh), or
   `mw container restart <id>`. After swapping postgres, **restart
   search/subscription** so Flyway re-runs against the fresh DB (otherwise the
   pooled connection reconnects to an empty DB and queries hit missing tables).

Ports are **project-internal** only. To functionally test from your machine,
port-forward the gateway and curl locally:

```bash
mw container port-forward <api-gateway-container-id> 8092:8080 -p p-example
curl http://localhost:8092/ngsi-ld/v1/entities?type=NachhaltigkeitsIndikator \
  -H 'Accept: application/ld+json'
```

> **Security:** auth is disabled, so the broker is an **open, writable** NGSI-LD
> endpoint. Keep it project-internal — do **not** attach a public ingress without
> first enabling Keycloak auth. The staging dashboard should reach it over the
> project-internal network (or a protected ingress), not a public URL.
