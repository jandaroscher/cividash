#!/usr/bin/env bash
#
# Tear down the local Stellio NGSI-LD broker and remove its volumes (clean
# slate). Run with no arguments — `down -v` always removes the named postgres
# volume.
#
# Usage:
#   ./docker/civitas/v1.6.2/teardown.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"
ENV_FILE="${SCRIPT_DIR}/.env"

command -v docker >/dev/null 2>&1 || { echo "ERROR: docker not found on PATH." >&2; exit 1; }

# Use .env if present so compose resolves the same project/volume names.
if [[ -f "${ENV_FILE}" ]]; then
  DC=(docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}")
else
  DC=(docker compose -f "${COMPOSE_FILE}")
fi

echo "Stopping Stellio NGSI-LD stack and removing volumes..."
"${DC[@]}" down -v --remove-orphans

echo "Done. Stack stopped and data volumes removed."
