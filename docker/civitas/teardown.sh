#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Stop all CIVITAS/CORE dev containers and the frontend dev server.
# Use --volumes to also remove persistent data (clean slate).
# ---------------------------------------------------------------------------
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# Must match the default in setup.sh.
CORE_DIR="${CIVITAS_CORE_DIR:-$(cd "$SCRIPT_DIR/../../.." && pwd)/civitas-core}"
FROST_DB_PASSWORD="${FROST_DB_PASSWORD:-frost_secret}"

GREEN='\033[0;32m'
NC='\033[0m'
info() { echo -e "${GREEN}[INFO]${NC}  $*"; }

VOLUME_FLAG=""
if [ "${1:-}" = "--volumes" ]; then
    VOLUME_FLAG="-v"
    info "Will remove volumes (clean slate)"
fi

# -------------------------------------------------------
# 1. Stop Frontend dev server
# -------------------------------------------------------
if [ -f /tmp/civitas-frontend.pid ]; then
    PID=$(cat /tmp/civitas-frontend.pid)
    if kill -0 "$PID" 2>/dev/null; then
        info "Stopping Frontend (PID: $PID)..."
        kill "$PID" 2>/dev/null || true
    fi
    rm -f /tmp/civitas-frontend.pid
fi
# Also kill any orphaned next dev processes for CORE
pkill -f "next dev.*civitas-core" 2>/dev/null || true

# -------------------------------------------------------
# 2. Stop application containers
# -------------------------------------------------------
for CONTAINER in civitas-portal-backend civitas-config-adapter; do
    if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
        info "Stopping ${CONTAINER}..."
        docker stop "$CONTAINER" 2>/dev/null || true
        docker rm "$CONTAINER" 2>/dev/null || true
    fi
done

# -------------------------------------------------------
# 3. Stop infrastructure containers
# -------------------------------------------------------
if [ ! -d "$CORE_DIR/dev-environment" ]; then
    echo "CORE directory not found at $CORE_DIR"
    exit 1
fi

info "Stopping FROST..."
(cd "$CORE_DIR/dev-environment/frost" && FROST_DB_PASSWORD="$FROST_DB_PASSWORD" docker compose down $VOLUME_FLAG) 2>/dev/null || true

info "Stopping APISIX..."
(cd "$CORE_DIR/dev-environment/apisix" && docker compose down $VOLUME_FLAG) 2>/dev/null || true

info "Stopping Keycloak..."
(cd "$CORE_DIR/dev-environment/keycloak" && docker compose down $VOLUME_FLAG) 2>/dev/null || true

info "Stopping Kafka..."
(cd "$CORE_DIR/dev-environment/kafka" && docker compose down $VOLUME_FLAG) 2>/dev/null || true

info "Stopping PostgreSQL..."
(cd "$CORE_DIR/dev-environment/postgres" && docker compose down $VOLUME_FLAG) 2>/dev/null || true

if [ -n "$VOLUME_FLAG" ]; then
    info "Removing Docker network..."
    docker network rm civitas-network 2>/dev/null || true
fi

info "All CIVITAS/CORE services stopped"
