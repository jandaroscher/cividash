# CIVITAS/CORE Local Dev Environment

Local development setup for testing the dashboard's CIVITAS/CORE integration against a real CORE instance.

## Prerequisites

- Docker + Docker Compose v2
- jq
- ~4 GB RAM free for containers

## Quick Start

```bash
# Start everything (clones CORE repo on first run)
./docker/civitas/setup.sh

# Stop containers (keeps data)
./docker/civitas/teardown.sh

# Stop and remove all data (clean slate)
./docker/civitas/teardown.sh --volumes
```

## Services

| Service | URL | Credentials |
|---------|-----|-------------|
| FROST SensorThings | http://localhost:8085/FROST-Server/v1.1 | - |
| Keycloak | http://localhost:8080 | admin / admin |
| Kafka UI | http://localhost:8090 | - |
| PostgreSQL (Portal) | localhost:5432 | - |
| PostgreSQL (Keycloak) | localhost:5433 | keycloak / keycloak |

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
| Erneuerbare Energien | PV-Leistung (MW), Gesamte Erneuerbare (MW) | 2015–2023 |
| Radverkehr | Modal Split Radverkehr (%) | 2015–2022 |
| CO2-Emissionen | CO2 pro Kopf (t CO2/a) | 2015–2022 |

## Customisation

- **Test data**: Edit `seed-data.json` to add/change indicators
- **CORE repo location**: Set `CIVITAS_CORE_DIR` env variable (default: `../civitas-core`)
- **FROST DB password**: Set `FROST_DB_PASSWORD` env variable (default: `frost_secret`)
