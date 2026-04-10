# CIVITAS/CORE Local Dev Environment

Local development setup for testing the dashboard's CIVITAS/CORE integration against a real CORE V2 instance.

## Quick Start

```bash
# Infrastructure only (FROST + Keycloak + Kafka — for API integration work)
./docker/civitas/setup.sh

# Full CORE Portal (includes Backend API, Config Adapter, Frontend UI)
./docker/civitas/setup.sh --full

# Stop everything (keeps data)
./docker/civitas/teardown.sh

# Stop and remove all data (clean slate)
./docker/civitas/teardown.sh --volumes
```

## Prerequisites

**Infrastructure only** (default):
- Docker + Docker Compose v2
- git (setup.sh clones the CORE repo)
- curl (used for health checks and FROST seeding)
- jq

**Full mode** (`--full`):
- All of the above, plus:
- Java 21+ JDK (`brew install openjdk@21`)
- Maven 3.9+ (`brew install maven`)
- pnpm (for the Next.js frontend)

## Services

### Infrastructure (always started)

| Service | URL | Credentials |
|---------|-----|-------------|
| FROST SensorThings | http://localhost:8085/FROST-Server/v1.1 | - |
| Keycloak | http://localhost:8080 | admin / admin |
| Kafka UI | http://localhost:8090 | - |
| APISIX Gateway | http://localhost:9080 | - |
| PostgreSQL (Portal) | localhost:5432 | admin / admin |
| PostgreSQL (Keycloak) | localhost:5433 | keycloak / keycloak |

### CORE Portal (--full mode)

| Service | URL | Notes |
|---------|-----|-------|
| Portal Frontend | http://localhost:3000 | Next.js 15 |
| Portal Backend API | http://localhost:8089 | Spring Boot |
| Swagger UI | http://localhost:8089/v1/swagger-ui/index.html | API docs |
| Config Adapter | http://localhost:8088 | Keycloak sync |

**Portal Login:** `dev@civitas.local` / `dev123`

## Dashboard .env

Add these to your `.env` to connect the dashboard to the local CORE instance:

```dotenv
CIVITAS_ENABLED=true
CIVITAS_API_URL=http://localhost:8085/FROST-Server/v1.1
CIVITAS_OAUTH_TOKEN_URL=http://localhost:8080/realms/civitas-core/protocol/openid-connect/token
CIVITAS_OAUTH_CLIENT_ID=cividash-dashboard
CIVITAS_OAUTH_CLIENT_SECRET=cividash-secret
```

## Test Data

The setup script seeds FROST with three sustainability indicators:

| Thing | Datastreams | Years |
|-------|------------|-------|
| Erneuerbare Energien | PV-Leistung (MW), Gesamte Erneuerbare (MW) | 2015-2023 |
| Radverkehr | Modal Split Radverkehr (%) | 2015-2022 |
| CO2-Emissionen | CO2 pro Kopf (t CO2/a) | 2015-2022 |

## Known Issues

- **Config Adapter**: Only the Keycloak adapter is loaded (APISIX/FROST/Redpanda adapters
  are not bundled in the fat JAR). This means APISIX routes are not auto-configured, so the
  frontend connects directly to the backend (port 8089) instead of through APISIX (port 9080).
- **Mailpit port**: If port 8025 is already in use (e.g. by DDEV), Mailpit fails to start.
  This is non-critical — only affects Keycloak email verification in dev.

## Customisation

- **Test data**: Edit `seed-data.json` to add/change indicators
- **CORE repo location**: Set `CIVITAS_CORE_DIR` env variable (default: `../civitas-core`)
- **FROST DB password**: Set `FROST_DB_PASSWORD` env variable (default: `frost_secret`)
