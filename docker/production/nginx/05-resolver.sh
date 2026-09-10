#!/bin/sh
# Fill the nginx `resolver` directive at container start.
#
# The PHP location proxies to a variable upstream (cividash-fpm:9000) so nginx
# re-resolves the name per request. That needs an explicit resolver, and the
# right one differs per platform: Docker/compose stacks resolve service names
# only via the embedded DNS at 127.0.0.11 (which may not be listed first, or at
# all, in /etc/resolv.conf); on Kubernetes the cluster DNS from resolv.conf
# does it. Pick the first candidate that can actually resolve the FPM host.
# NGINX_RESOLVER skips the probing.
set -eu
fpm_host="${CIVIDASH_FPM_HOST:-cividash-fpm}"
if [ -n "${NGINX_RESOLVER:-}" ]; then
  resolver="$NGINX_RESOLVER"
else
  candidates="127.0.0.11 $(awk '/^nameserver/ { print $2 }' /etc/resolv.conf)"
  resolver=""
  for ns in $candidates; do
    if nslookup "$fpm_host" "$ns" >/dev/null 2>&1; then
      resolver="$ns"
      break
    fi
  done
  if [ -z "$resolver" ]; then
    resolver="$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)"
    : "${resolver:=127.0.0.11}"
    echo "cividash-web: no nameserver could resolve ${fpm_host} yet, falling back to ${resolver}" >&2
  fi
fi
sed -i "s|__RESOLVER__|${resolver}|" /etc/nginx/conf.d/default.conf
echo "cividash-web: nginx resolver set to ${resolver}"
