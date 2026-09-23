#!/bin/sh
#
# Keycloak entrypoint baked into the optional cividash-keycloak image.
#
# Contract:
#   - The Keycloak base image ships NO envsubst, so this script uses sed to
#     inject the two runtime secrets (CLIENT_SECRET, DEMO_PASSWORD) and the
#     dashboard origin (DASHBOARD_URL) into the committed realm template, which
#     carries only "__PLACEHOLDER__" literals.
#   - Runs as uid 1000 (Keycloak default) and only writes under
#     /opt/keycloak/data/... which is owned by that user.
#   - Hands off to kc.sh start --optimized --import-realm on the server the
#     Dockerfile pre-built (H2, demo only; not hardened for production).
#
set -e

TEMPLATE="/opt/keycloak/realm-civitas.json"
IMPORT_DIR="/opt/keycloak/data/import"
TARGET="${IMPORT_DIR}/realm-civitas.json"

if [ -z "${CLIENT_SECRET}" ]; then
    echo "[keycloak-entrypoint] ERROR: CLIENT_SECRET is not set" >&2
    exit 1
fi
if [ -z "${DEMO_PASSWORD}" ]; then
    echo "[keycloak-entrypoint] ERROR: DEMO_PASSWORD is not set" >&2
    exit 1
fi

# Matches KEYCLOAK_REDIRECT_URI in .env.example, so a local run works unconfigured.
DASHBOARD_URL="${DASHBOARD_URL:-http://localhost:8000}"

mkdir -p "${IMPORT_DIR}"

# The secrets land inside JSON double-quoted strings, so they are escaped in two
# stages so arbitrary values stay valid JSON AND a safe sed replacement string:
#   1) JSON-string escape: backslash then double-quote.
#   2) sed replacement escape: backslash again, then the delimiter (/) and &.
esc() {
    printf '%s' "$1" \
        | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' \
        | sed -e 's/\\/\\\\/g' -e 's/[&/]/\\&/g'
}

CLIENT_SECRET_ESC=$(esc "${CLIENT_SECRET}")
DEMO_PASSWORD_ESC=$(esc "${DEMO_PASSWORD}")
DASHBOARD_URL_ESC=$(esc "${DASHBOARD_URL%/}")

sed \
    -e "s/__CLIENT_SECRET__/${CLIENT_SECRET_ESC}/g" \
    -e "s/__DEMO_PASSWORD__/${DEMO_PASSWORD_ESC}/g" \
    -e "s/__DASHBOARD_URL__/${DASHBOARD_URL_ESC}/g" \
    "${TEMPLATE}" > "${TARGET}"

echo "[keycloak-entrypoint] realm import written to ${TARGET}; starting Keycloak"

exec /opt/keycloak/bin/kc.sh start --optimized --import-realm
