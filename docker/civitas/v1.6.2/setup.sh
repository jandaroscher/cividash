#!/usr/bin/env bash
#
# Bring up the local Stellio NGSI-LD broker (CORE V1.6.2-aligned) and seed it
# with the 3 NachhaltigkeitsIndikator entities for Pull-pipeline validation.
#
# BOUNDED: never spins longer than ~5 minutes total on readiness. On timeout it
# dumps `docker compose logs --tail=80` and exits non-zero (reports a blocker)
# instead of looping forever.
#
# Idempotent: re-running re-POSTs the seed entities; an already-existing entity
# (HTTP 409) is treated as success.
#
# Usage:
#   ./docker/civitas/v1.6.2/setup.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"
ENV_FILE="${SCRIPT_DIR}/.env"
ENV_EXAMPLE="${SCRIPT_DIR}/.env.example"
SEED_FILE="${SCRIPT_DIR}/seed-ngsi-ld.json"

# ---------------------------------------------------------------------------
# Prerequisites
# ---------------------------------------------------------------------------
command -v docker >/dev/null 2>&1 || { echo "ERROR: docker not found on PATH." >&2; exit 1; }
command -v jq >/dev/null 2>&1 || { echo "ERROR: jq not found on PATH (brew install jq)." >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "ERROR: curl not found on PATH." >&2; exit 1; }

# Auto-create .env from the example on first run.
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "No .env found — creating one from .env.example."
  cp "${ENV_EXAMPLE}" "${ENV_FILE}"
fi

# Read the host port from .env (defaults to 8090 if unset).
API_GATEWAY_HOST_PORT="$(grep -E '^API_GATEWAY_HOST_PORT=' "${ENV_FILE}" | head -n1 | cut -d= -f2- || true)"
API_GATEWAY_HOST_PORT="${API_GATEWAY_HOST_PORT:-8090}"

BASE_URL="http://localhost:${API_GATEWAY_HOST_PORT}/ngsi-ld/v1"
ENTITIES_URL="${BASE_URL}/entities"

DC=(docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}")

dump_logs_and_fail() {
  local msg="$1"
  echo "" >&2
  echo "BLOCKER: ${msg}" >&2
  echo "--- docker compose logs (tail 80) ---" >&2
  "${DC[@]}" logs --tail=80 >&2 || true
  exit 1
}

# ---------------------------------------------------------------------------
# 1) Bring the stack up (bounded by compose --wait timeout)
# ---------------------------------------------------------------------------
echo "Starting Stellio NGSI-LD stack (postgres + kafka + search + subscription + gateway)..."
if ! "${DC[@]}" up -d --wait --wait-timeout 180; then
  dump_logs_and_fail "docker compose up --wait did not become healthy within 180s."
fi

# ---------------------------------------------------------------------------
# 2) Wait for the NGSI-LD endpoint to answer (bounded — total cap ~5 min)
#    Ready = GET /entities?type=...&count=true returns HTTP 200 and the
#    NGSILD-Results-Count header is present (what our NgsiLdClient reads).
# ---------------------------------------------------------------------------
echo "Waiting for NGSI-LD endpoint at ${ENTITIES_URL} ..."
READY=0
DEADLINE=$(( $(date +%s) + 120 ))   # api-gateway routing readiness; ~2 min on top of the 180s above
while [[ $(date +%s) -lt ${DEADLINE} ]]; do
  HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' \
    -H 'Accept: application/ld+json' \
    "${ENTITIES_URL}?type=NachhaltigkeitsIndikator&limit=1&count=true" \
    -H 'Link: <https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld>; rel="http://www.w3.org/ns/json-ld#context"; type="application/ld+json"' \
    || echo 000)"
  if [[ "${HTTP_CODE}" == "200" ]]; then
    READY=1
    break
  fi
  echo "  ... gateway not ready yet (HTTP ${HTTP_CODE}); retrying in 5s"
  sleep 5
done

if [[ "${READY}" -ne 1 ]]; then
  dump_logs_and_fail "NGSI-LD endpoint did not return HTTP 200 within the bounded window."
fi
echo "NGSI-LD endpoint is up."

# ---------------------------------------------------------------------------
# 3) Seed the 3 indicators. Each entity carries its own @context, so POST each
#    one individually as application/ld+json. 409 (already exists) => success.
# ---------------------------------------------------------------------------
echo "Seeding indicators from $(basename "${SEED_FILE}") ..."
COUNT="$(jq 'length' "${SEED_FILE}")"
for i in $(seq 0 $((COUNT - 1))); do
  ENTITY="$(jq -c ".[${i}]" "${SEED_FILE}")"
  ENTITY_ID="$(jq -r ".[${i}].id" "${SEED_FILE}")"

  RESP="$(curl -s -o /dev/null -w '%{http_code}' \
    -X POST "${ENTITIES_URL}" \
    -H 'Content-Type: application/ld+json' \
    --data-binary "${ENTITY}" || echo 000)"

  case "${RESP}" in
    201) echo "  created: ${ENTITY_ID}" ;;
    409) echo "  exists (ok): ${ENTITY_ID}" ;;
    *)   dump_logs_and_fail "POST ${ENTITY_ID} returned HTTP ${RESP} (expected 201 or 409)." ;;
  esac
done

# ---------------------------------------------------------------------------
# 4) Verify the count the dashboard's NgsiLdClient will read.
# ---------------------------------------------------------------------------
RESULT_COUNT="$(curl -s -D - -o /dev/null \
  -H 'Accept: application/ld+json' \
  "${ENTITIES_URL}?type=NachhaltigkeitsIndikator&limit=1&count=true" \
  | tr -d '\r' | awk -F': ' 'tolower($1)=="ngsild-results-count"{print $2}')"
echo "NGSILD-Results-Count for type=NachhaltigkeitsIndikator: ${RESULT_COUNT:-<none>}"

# ---------------------------------------------------------------------------
# Done — print the CIVITAS_API_URL to use from DDEV.
# ---------------------------------------------------------------------------
cat <<EOF

============================================================================
Stellio NGSI-LD broker is up and seeded.

  Host (browser/curl):  ${BASE_URL}
  From inside DDEV:      http://host.docker.internal:${API_GATEWAY_HOST_PORT}/ngsi-ld/v1

For a local sync proof, set these in the Laravel app's root .env (gitignored):

  CIVITAS_ENABLED=true
  CIVITAS_DRIVER=ngsi-ld
  CIVITAS_API_URL=http://host.docker.internal:${API_GATEWAY_HOST_PORT}/ngsi-ld/v1
  # Leave CIVITAS_OAUTH_* empty — auth is disabled, no token needed.

Then run the sync from DDEV, e.g.:

  ddev exec php artisan integration:sync-civitas --tenant=<slug> --dry-run   # preview
  ddev exec php artisan integration:sync-civitas --tenant=<slug>             # persist

Tear down with:  ./docker/civitas/v1.6.2/teardown.sh
============================================================================
EOF
