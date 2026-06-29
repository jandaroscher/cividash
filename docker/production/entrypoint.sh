#!/bin/sh
#
# CiviDash production entrypoint (baked into the cividash-app image).
#
# Contract:
#   - Runs AFTER the orchestrator has injected env vars. config:cache freezes
#     the current env into the compiled config, so caching MUST happen here at
#     container start — not at image build time.
#   - Warms the Laravel + Filament caches (config, routes, views, Filament
#     components), then hands off to the container CMD via `exec "$@"`.
#   - Optional caches are best-effort: a failure is logged and tolerated so a
#     single bad cache does not crash an otherwise healthy container.
#   - Does NOT run database migrations — that is the dedicated cividash-migrate job.
#
set -e

log() {
    echo "[entrypoint] $*"
}

# Best-effort cache warmer: log on failure, keep the boot going.
try_cache() {
    log "running: $*"
    if ! "$@"; then
        log "WARNING: '$*' failed; continuing without this cache"
    fi
}

log "warming application caches"

# Clear any stale compiled caches first so a baked-in or previous-run cache
# never shadows the freshly injected runtime env.
try_cache php artisan config:clear

try_cache php artisan config:cache
try_cache php artisan route:cache
try_cache php artisan view:cache
try_cache php artisan filament:cache-components

log "cache warmup complete; starting: $*"

exec "$@"
