#!/bin/sh
# Fill the nginx `resolver` directive and the FPM upstream at container start.
#
# The PHP location proxies to a variable upstream so nginx re-resolves the FPM
# name per request (survives a cividash-fpm recreate). nginx's resolver ignores
# resolv.conf search domains, so the upstream must be the name the resolver
# answers for. Which search domain that is differs per cluster (kind/CORE:
# <ns>.svc.cluster.local; other platforms often use the last search domain),
# so probe the plain name and every search domain against the resolver until
# one answers, waiting a while because the FPM service may not exist yet during
# a stack update. Docker compose (no KUBERNETES_SERVICE_HOST) uses the
# embedded DNS 127.0.0.11 and the plain name.
# NGINX_RESOLVER and CIVIDASH_FPM_UPSTREAM override the detection.
set -eu
fpm_host="${CIVIDASH_FPM_HOST:-cividash-fpm}"
if [ -n "${KUBERNETES_SERVICE_HOST:-}" ]; then
  default_resolver="$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)"
else
  default_resolver="127.0.0.11"
fi
resolver="${NGINX_RESOLVER:-${default_resolver:-127.0.0.11}}"

if [ -n "${CIVIDASH_FPM_UPSTREAM:-}" ]; then
  upstream="$CIVIDASH_FPM_UPSTREAM"
elif [ -z "${KUBERNETES_SERVICE_HOST:-}" ]; then
  upstream="${fpm_host}:9000"
else
  candidates="$fpm_host"
  for d in $(awk '/^search/ { $1=""; print }' /etc/resolv.conf); do
    candidates="$candidates ${fpm_host}.${d}"
  done
  upstream=""
  deadline=$(( $(date +%s) + ${CIVIDASH_FPM_RESOLVE_TIMEOUT:-90} ))
  while [ -z "$upstream" ] && [ "$(date +%s)" -lt "$deadline" ]; do
    for c in $candidates; do
      if nslookup "$c" "$resolver" >/dev/null 2>&1; then
        upstream="${c}:9000"
        break
      fi
    done
    [ -n "$upstream" ] || sleep 3
  done
  if [ -z "$upstream" ]; then
    first_search="$(awk '/^search/ { print $2; exit }' /etc/resolv.conf)"
    upstream="${fpm_host}${first_search:+.$first_search}:9000"
    echo "cividash-web: could not resolve ${fpm_host} via ${resolver} within the timeout, using ${upstream}" >&2
  fi
fi

sed -i "s|__RESOLVER__|${resolver}|; s|__FPM_UPSTREAM__|${upstream}|" /etc/nginx/conf.d/default.conf
echo "cividash-web: resolver ${resolver}, fpm upstream ${upstream} (resolv.conf: $(grep -E '^(nameserver|search)' /etc/resolv.conf | tr '\n' ';'))"
