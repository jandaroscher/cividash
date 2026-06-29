#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# CIVITAS/CORE local dev environment setup
#
# Clones the CORE V2 repo (if needed), builds and starts all services
# (infrastructure + Portal Backend/Frontend + Config Adapter), creates a
# Keycloak client for the dashboard, and seeds FROST with test data.
#
# Prerequisites: Docker + Compose v2, Java 21+ JDK, Maven 3.9+, jq, pnpm
# Usage:         ./docker/civitas/setup.sh [--full]
# Teardown:      ./docker/civitas/teardown.sh
#
# Flags:
#   --full    Build and start Portal Backend, Config Adapter, and Frontend
#             (requires Java 21+, Maven 3.9+, pnpm). Without this flag,
#             only infrastructure + FROST + Keycloak are started.
# ---------------------------------------------------------------------------
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# Defaults to a civitas-core checkout next to this repository.
CORE_DIR="${CIVITAS_CORE_DIR:-$(cd "$SCRIPT_DIR/../../.." && pwd)/civitas-core}"
CORE_REPO="https://gitlab.com/civitas-connect/civitas-core/civitas-core-v2/civitas-core-platform.git"

FROST_URL="http://localhost:8085/FROST-Server/v1.1"
KC_URL="http://localhost:8080"
KC_REALM="civitas-core"
KC_ADMIN_USER="admin"
KC_ADMIN_PASS="admin"
KC_CLIENT_ID="cividash-dashboard"
KC_CLIENT_SECRET="cividash-secret"
FROST_DB_PASSWORD="${FROST_DB_PASSWORD:-frost_secret}"

FULL_MODE=false
if [ "${1:-}" = "--full" ]; then
    FULL_MODE=true
fi

# Colours
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()   { echo -e "${RED}[ERR]${NC}   $*"; }

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
(cd "$CORE_DIR/dev-environment/postgres" && docker compose up -d) 2>&1 | grep -v "Pulling"

info "Starting Kafka..."
(cd "$CORE_DIR/dev-environment/kafka" && docker compose up -d) 2>&1 | grep -v "Pulling"

info "Starting Keycloak..."
(cd "$CORE_DIR/dev-environment/keycloak" && docker compose up -d) 2>&1 | grep -v "Pulling" || warn "Keycloak compose had warnings (Mailpit port conflict is non-critical)"

info "Starting APISIX..."
(cd "$CORE_DIR/dev-environment/apisix" && docker compose up -d) 2>&1 | grep -v "Pulling"

info "Starting FROST (SensorThings)..."
(cd "$CORE_DIR/dev-environment/frost" && FROST_DB_PASSWORD="$FROST_DB_PASSWORD" docker compose up -d) 2>&1 | grep -v "Pulling"

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
curl -sf "$KC_URL/realms/$KC_REALM" &>/dev/null || { err "Keycloak not ready after 120s"; exit 1; }
info "Keycloak ready"

info "Waiting for FROST to be ready..."
for i in $(seq 1 30); do
    if curl -sf "$FROST_URL" &>/dev/null; then
        break
    fi
    sleep 2
done
curl -sf "$FROST_URL" &>/dev/null || { err "FROST not ready after 60s"; exit 1; }
info "FROST ready"

# -------------------------------------------------------
# 5. Configure Keycloak: disable SSL + create dashboard client
# -------------------------------------------------------
info "Configuring Keycloak..."

docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh config credentials \
    --server http://localhost:8080 --realm master \
    --user "$KC_ADMIN_USER" --password "$KC_ADMIN_PASS" 2>/dev/null

# Disable SSL for local dev (Keycloak 26+ enforces HTTPS on external requests)
docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh update realms/master \
    -s sslRequired=NONE 2>/dev/null
docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh update realms/$KC_REALM \
    -s sslRequired=NONE 2>/dev/null

# Create dashboard OAuth2 client (Client Credentials grant for API access)
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
        -s standardFlowEnabled=true \
        -s 'redirectUris=["http://localhost:*"]' \
        -s protocol=openid-connect \
        -s publicClient=false 2>/dev/null
    info "Client created"
else
    info "Keycloak client '$KC_CLIENT_ID' already exists — ensuring Authorization Code flow is enabled"
    CLIENT_UUID=$(docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh get clients \
        -r "$KC_REALM" -q clientId="$KC_CLIENT_ID" --fields id 2>/dev/null | grep '"id"' | sed 's/.*: "\(.*\)".*/\1/')
    if [ -n "$CLIENT_UUID" ]; then
        docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh update "clients/$CLIENT_UUID" \
            -r "$KC_REALM" -s standardFlowEnabled=true 2>/dev/null
    fi
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
# 7. Full mode: Build & start Portal Backend, Config Adapter, Frontend
# -------------------------------------------------------
if [ "$FULL_MODE" = true ]; then
    info "=== Full mode: building CORE Portal ==="

    # Check prerequisites
    if command -v java &>/dev/null; then
        java_version=$(java -version 2>&1 | head -1 | sed 's/.*"\([0-9]*\)\..*/\1/')
        if ! [ "$java_version" -ge 21 ] 2>/dev/null; then
            err "Java 21+ required (found version ${java_version:-unknown}). Install with: brew install openjdk@21"
            exit 1
        fi
    elif [ -x "/usr/local/opt/openjdk@21/bin/java" ]; then
        : # Will be added to PATH below
    else
        err "Java 21+ required. Install with: brew install openjdk@21"
        exit 1
    fi
    if ! command -v mvn &>/dev/null; then
        err "Maven 3.9+ required. Install with: brew install maven"
        exit 1
    fi

    # Use Java 21 if available
    if [ -d "/usr/local/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home" ]; then
        export JAVA_HOME="/usr/local/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home"
        export PATH="$JAVA_HOME/bin:$PATH"
    fi

    # -- Config Adapter --
    info "Building Config Adapter..."
    (cd "$CORE_DIR/config-adapter" && mvn package -DskipTests -q)
    docker build -t civitas-config-adapter -f "$CORE_DIR/config-adapter/Dockerfile" "$CORE_DIR/config-adapter/" -q

    docker rm -f civitas-config-adapter 2>/dev/null || true
    info "Starting Config Adapter (keycloak adapter only)..."
    docker run -d --name civitas-config-adapter \
        --network civitas-network \
        -p 8088:8088 \
        -e ADAPTERS=keycloak \
        -e KAFKA_BOOTSTRAP_SERVERS=civitas-kafka:29092 \
        -e KEYCLOAK_URL=http://civitas-keycloak:8080 \
        -e KEYCLOAK_PASSWORD=admin \
        -e SERVER_PORT=8088 \
        -e PORT=8088 \
        -e HEALTHCHECK_PORT=8088 \
        civitas-config-adapter >/dev/null

    # Wait for Config Adapter to be ready
    info "Waiting for Config Adapter..."
    for i in $(seq 1 30); do
        if curl -sf http://localhost:8088/health/ready &>/dev/null; then break; fi
        sleep 2
    done
    curl -sf http://localhost:8088/health/ready &>/dev/null && info "Config Adapter ready" || warn "Config Adapter not healthy yet"

    # -- Portal Backend --
    info "Building Portal Backend..."
    (cd "$CORE_DIR/portal-backend" && mvn package -DskipTests -q)
    docker build -t civitas-portal-backend -f "$CORE_DIR/portal-backend/Dockerfile" "$CORE_DIR/portal-backend/" -q

    docker rm -f civitas-portal-backend 2>/dev/null || true
    info "Starting Portal Backend..."
    docker run -d --name civitas-portal-backend \
        --network civitas-network \
        -p 8089:8089 \
        -e SPRING_DATASOURCE_URL=jdbc:postgresql://civitas-postgres-portal:5432/portal_backend \
        -e SPRING_DATASOURCE_USERNAME=admin \
        -e SPRING_DATASOURCE_PASSWORD=admin \
        -e SPRING_KAFKA_BOOTSTRAP_SERVERS=civitas-kafka:29092 \
        -e KAFKA_BOOTSTRAP_SERVERS=civitas-kafka:29092 \
        -e KEYCLOAK_AUTH_SERVER_URL=http://civitas-keycloak:8080 \
        -e KEYCLOAK_ISSUER_URI=http://localhost:8080 \
        -e KEYCLOAK_REALM=civitas-core \
        -e KEYCLOAK_TARGET_REALM=civitas-core \
        -e APP_URL=http://localhost:8089 \
        -e SERVER_PORT=8089 \
        -e SPRING_PROFILES_ACTIVE=local,postgres,init \
        -e SPRING_SECURITY_OAUTH2_RESOURCESERVER_JWT_ISSUER_URI=http://localhost:8080/realms/civitas-core \
        -e SPRING_SECURITY_OAUTH2_RESOURCESERVER_JWT_JWK_SET_URI=http://civitas-keycloak:8080/realms/civitas-core/protocol/openid-connect/certs \
        civitas-portal-backend >/dev/null

    # Wait for backend health
    info "Waiting for Portal Backend (Spring Boot startup ~15s)..."
    for i in $(seq 1 40); do
        if curl -sf http://localhost:8089/v1/actuator/health &>/dev/null; then break; fi
        sleep 3
    done
    curl -sf http://localhost:8089/v1/actuator/health &>/dev/null && info "Portal Backend ready" || warn "Portal Backend not healthy yet"

    # Set dev user password in Keycloak (backend init creates user, config adapter syncs to KC)
    info "Setting dev user password..."
    docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh config credentials \
        --server http://localhost:8080 --realm master \
        --user "$KC_ADMIN_USER" --password "$KC_ADMIN_PASS" 2>/dev/null
    docker exec civitas-keycloak /opt/keycloak/bin/kcadm.sh set-password \
        -r civitas-core --username dev@civitas.local --new-password dev123 2>/dev/null \
        && info "Dev user password set" || warn "Could not set dev user password (user may not exist yet)"

    # -- Portal Frontend --
    info "Setting up Portal Frontend..."
    cd "$CORE_DIR/portal-frontend"

    # Configure .env.local: point API directly to backend (bypass APISIX)
    if [ ! -f .env.local ]; then
        cp .env.local.template .env.local
    fi
    # Patch API port to backend (8089) instead of APISIX (9080).
    # Portable in-place edit that works on both BSD (macOS) and GNU (Linux)
    # sed without relying on the incompatible `-i` flag syntax.
    if grep -q 'API_PORT=9080' .env.local; then
        tmp_env="$(mktemp)"
        sed 's/API_PORT=9080/API_PORT=8089/' .env.local > "$tmp_env" \
            && mv "$tmp_env" .env.local \
            || { rm -f "$tmp_env"; err "Failed to patch API_PORT in .env.local"; exit 1; }
        info "Patched .env.local API_PORT to 8089"
    else
        warn "API_PORT=9080 not found in .env.local — skipping port patch (template may have changed)"
    fi

    corepack enable 2>/dev/null || true
    pnpm install --frozen-lockfile 2>/dev/null || pnpm install

    info "Starting Portal Frontend (Next.js dev server)..."
    nohup pnpm dev > /tmp/civitas-frontend.log 2>&1 &
    FRONTEND_PID=$!
    echo "$FRONTEND_PID" > /tmp/civitas-frontend.pid

    # Wait for frontend
    for i in $(seq 1 15); do
        if curl -sf http://localhost:3000 -o /dev/null 2>/dev/null; then break; fi
        sleep 2
    done
    curl -sf http://localhost:3000 -o /dev/null 2>/dev/null && info "Portal Frontend ready (PID: $FRONTEND_PID)" || warn "Frontend not ready yet, check /tmp/civitas-frontend.log"

    cd "$SCRIPT_DIR/../.."
fi

# -------------------------------------------------------
# 8. Verify & print summary
# -------------------------------------------------------
echo ""
info "=========================================="
info " CIVITAS/CORE Dev Environment Ready"
info "=========================================="
echo ""
echo "  Infrastructure:"
echo "    FROST SensorThings  http://localhost:8085/FROST-Server/v1.1"
echo "    Keycloak            http://localhost:8080  (admin/admin)"
echo "    Kafka UI            http://localhost:8090"
echo "    PostgreSQL (Portal) localhost:5432"
echo ""

if [ "$FULL_MODE" = true ]; then
echo "  CORE Portal:"
echo "    Frontend            http://localhost:3000"
echo "    Backend API         http://localhost:8089"
echo "    Swagger UI          http://localhost:8089/v1/swagger-ui/index.html"
echo "    Config Adapter      http://localhost:8088"
echo ""
echo "  Portal Login:"
echo "    Username: dev@civitas.local"
echo "    Password: dev123"
echo ""
fi

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
