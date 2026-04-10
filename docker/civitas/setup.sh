#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# CIVITAS/CORE local dev environment setup
#
# Clones the CORE V2 repo (if needed), starts infrastructure containers,
# creates a Keycloak client for the dashboard, and seeds FROST with
# sustainability test data.
#
# Prerequisites: Docker + Compose v2, jq
# Usage:         ./docker/civitas/setup.sh
# Teardown:      ./docker/civitas/teardown.sh
# ---------------------------------------------------------------------------
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# Must match the default in setup.sh.
CORE_DIR="${CIVITAS_CORE_DIR:-$(cd "$SCRIPT_DIR/../../.." && pwd)/civitas-core}"
CORE_REPO="https://gitlab.com/civitas-connect/civitas-core/civitas-core-v2/civitas-core-platform.git"

FROST_URL="http://localhost:8085/FROST-Server/v1.1"
KC_URL="http://localhost:8080"
KC_REALM="civitas-core"
KC_ADMIN_USER="admin"
KC_ADMIN_PASS="admin"
KC_CLIENT_ID="cividash-dashboard"
KC_CLIENT_SECRET="cividash-secret"
FROST_DB_PASSWORD="frost_secret"

# Colours
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }

# -------------------------------------------------------
# 1. Clone CORE repo if not present
# -------------------------------------------------------
if [ ! -d "$CORE_DIR" ]; then
    info "Cloning CIVITAS/CORE V2 to ${CORE_DIR}..."
    git clone "$CORE_REPO" "$CORE_DIR"
else
    info "CORE repo already present at ${CORE_DIR}"
fi

# -------------------------------------------------------
# 2. Create Docker network
# -------------------------------------------------------
if ! docker network inspect civitas-network &>/dev/null; then
    info "Creating Docker network 'civitas-network'..."
    docker network create civitas-network
else
    info "Docker network 'civitas-network' already exists"
fi

# -------------------------------------------------------
# 3. Start infrastructure containers
# -------------------------------------------------------
info "Starting PostgreSQL..."
(cd "$CORE_DIR/dev-environment/postgres" && docker compose up -d)

info "Starting Kafka..."
(cd "$CORE_DIR/dev-environment/kafka" && docker compose up -d)

info "Starting Keycloak..."
(cd "$CORE_DIR/dev-environment/keycloak" && docker compose up -d) || warn "Mailpit port conflict is non-critical"

info "Starting FROST (SensorThings)..."
(cd "$CORE_DIR/dev-environment/frost" && FROST_DB_PASSWORD="$FROST_DB_PASSWORD" docker compose up -d)

# -------------------------------------------------------
# 4. Wait for services to be ready
# -------------------------------------------------------
info "Waiting for Keycloak to be ready..."
for i in $(seq 1 60); do
    if curl -sf "$KC_URL/realms/$KC_REALM" &>/dev/null; then
        break
    fi
    sleep 2
done
curl -sf "$KC_URL/realms/$KC_REALM" &>/dev/null || { echo "Keycloak not ready after 120s"; exit 1; }
info "Keycloak ready"

info "Waiting for FROST to be ready..."
for i in $(seq 1 30); do
    if curl -sf "$FROST_URL" &>/dev/null; then
        break
    fi
    sleep 2
done
curl -sf "$FROST_URL" &>/dev/null || { echo "FROST not ready after 60s"; exit 1; }
info "FROST ready"

# -------------------------------------------------------
# 5. Configure Keycloak: disable SSL + create client
# -------------------------------------------------------
info "Configuring Keycloak..."

# Login with admin CLI inside container
docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh config credentials \
    --server http://localhost:8080 --realm master \
    --user "$KC_ADMIN_USER" --password "$KC_ADMIN_PASS" 2>/dev/null

# Disable SSL for local dev
docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh update realms/master \
    -s sslRequired=NONE 2>/dev/null
docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh update realms/$KC_REALM \
    -s sslRequired=NONE 2>/dev/null

# Check if client already exists
EXISTING=$(docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh get clients \
    -r "$KC_REALM" -q clientId="$KC_CLIENT_ID" 2>/dev/null | grep -c '"clientId"' || true)

if [ "$EXISTING" -eq 0 ]; then
    info "Creating Keycloak client '$KC_CLIENT_ID'..."
    docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh create clients \
        -r "$KC_REALM" \
        -s clientId="$KC_CLIENT_ID" \
        -s enabled=true \
        -s clientAuthenticatorType=client-secret \
        -s secret="$KC_CLIENT_SECRET" \
        -s serviceAccountsEnabled=true \
        -s directAccessGrantsEnabled=true \
        -s 'redirectUris=["http://localhost:*"]' \
        -s protocol=openid-connect \
        -s publicClient=false 2>/dev/null
    info "Client created"
else
    info "Keycloak client '$KC_CLIENT_ID' already exists"
fi

# -------------------------------------------------------
# 6. Seed FROST with sustainability test data
# -------------------------------------------------------
THINGS_COUNT=$(curl -sf "$FROST_URL/Things?\$count=true" | jq '.["@iot.count"]')

if [ "$THINGS_COUNT" -le 1 ]; then
    info "Seeding FROST with sustainability indicators..."
    jq -c '.[]' "$SCRIPT_DIR/seed-data.json" | while read -r entity; do
        NAME=$(echo "$entity" | jq -r '.name')
        curl -sf -X POST "$FROST_URL/Things" \
            -H "Content-Type: application/json" \
            -d "$entity" >/dev/null
        info "  Created: $NAME"
    done
else
    info "FROST already has $THINGS_COUNT Things, skipping seed"
fi

# -------------------------------------------------------
# 7. Verify & print summary
# -------------------------------------------------------
echo ""
info "=========================================="
info " CIVITAS/CORE Dev Environment Ready"
info "=========================================="
echo ""
echo "  Services:"
echo "    FROST SensorThings  http://localhost:8085/FROST-Server/v1.1"
echo "    Keycloak            http://localhost:8080  (admin/admin)"
echo "    Kafka UI            http://localhost:8090"
echo "    PostgreSQL (Portal) localhost:5432"
echo ""
echo "  Dashboard OAuth2 Credentials:"
echo "    Client ID:     $KC_CLIENT_ID"
echo "    Client Secret: $KC_CLIENT_SECRET"
echo "    Token URL:     $KC_URL/realms/$KC_REALM/protocol/openid-connect/token"
echo ""
echo "  .env variables for the dashboard:"
echo "    CIVITAS_ENABLED=true"
echo "    CIVITAS_API_URL=$FROST_URL"
echo "    CIVITAS_OAUTH_TOKEN_URL=$KC_URL/realms/$KC_REALM/protocol/openid-connect/token"
echo "    CIVITAS_OAUTH_CLIENT_ID=$KC_CLIENT_ID"
echo "    CIVITAS_OAUTH_CLIENT_SECRET=$KC_CLIENT_SECRET"
echo ""
echo "  Test: curl -s $FROST_URL/Things | jq '.value[].name'"
echo ""
