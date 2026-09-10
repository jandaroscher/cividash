#!/bin/sh
# Fill the nginx `resolver` directive at container start.
#
# The PHP location proxies to a variable upstream (cividash-fpm:9000) so nginx
# re-resolves the name per request. That needs an explicit resolver, and the
# right one differs per platform: Docker/compose stacks resolve service names
# only via the embedded DNS at 127.0.0.11 (not necessarily listed in
# /etc/resolv.conf, e.g. on the hosting provider); on Kubernetes the cluster DNS from
# resolv.conf does it. Detect the embedded DNS by resolving our own hostname
# through it: that works on any Docker network regardless of whether the FPM
# container is already up (it usually is not yet during a stack update), and
# is refused on Kubernetes. NGINX_RESOLVER skips the detection.
set -eu
if [ -n "${NGINX_RESOLVER:-}" ]; then
  resolver="$NGINX_RESOLVER"
elif nslookup "${CIVIDASH_WEB_HOST:-cividash-web}" 127.0.0.11 >/dev/null 2>&1; then
  resolver="127.0.0.11"
else
  resolver="$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)"
  : "${resolver:=127.0.0.11}"
fi
sed -i "s|__RESOLVER__|${resolver}|" /etc/nginx/conf.d/default.conf
echo "cividash-web: nginx resolver set to ${resolver}"
