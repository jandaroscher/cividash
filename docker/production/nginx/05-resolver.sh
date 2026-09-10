#!/bin/sh
# Fill the nginx `resolver` directive and the FPM upstream at container start.
#
# The PHP location proxies to a variable upstream so nginx re-resolves the FPM
# name per request (survives a cividash-fpm recreate). nginx's resolver ignores
# resolv.conf search domains, so the platform decides both values:
#   - Kubernetes (every pod has KUBERNETES_SERVICE_HOST; the hosting provider runs its
#     containers on Kubernetes too): cluster DNS from resolv.conf, and the FPM
#     name qualified with the first search domain (<ns>.svc.cluster.local).
#   - Docker compose (local, generic hosts): embedded DNS 127.0.0.11, plain
#     service name.
# NGINX_RESOLVER and CIVIDASH_FPM_UPSTREAM override the detection.
set -eu
fpm_host="${CIVIDASH_FPM_HOST:-cividash-fpm}"
if [ -n "${KUBERNETES_SERVICE_HOST:-}" ]; then
  default_resolver="$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)"
  search="$(awk '/^search/ { print $2; exit }' /etc/resolv.conf)"
  default_upstream="${fpm_host}${search:+.$search}:9000"
else
  default_resolver="127.0.0.11"
  default_upstream="${fpm_host}:9000"
fi
resolver="${NGINX_RESOLVER:-${default_resolver:-127.0.0.11}}"
upstream="${CIVIDASH_FPM_UPSTREAM:-$default_upstream}"
sed -i "s|__RESOLVER__|${resolver}|; s|__FPM_UPSTREAM__|${upstream}|" /etc/nginx/conf.d/default.conf
echo "cividash-web: resolver ${resolver}, fpm upstream ${upstream} (resolv.conf: $(grep -E '^(nameserver|search)' /etc/resolv.conf | tr '\n' ';'))"
