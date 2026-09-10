#!/bin/sh
# Fill the nginx `resolver` directive at container start.
#
# The PHP location proxies to a variable upstream (cividash-fpm:9000) so nginx
# re-resolves the name per request. That needs an explicit resolver, and the
# right one differs per platform: Docker/compose stacks (local, hosted)
# resolve service names via the embedded DNS at 127.0.0.11; Kubernetes pods
# use the cluster DNS listed in /etc/resolv.conf. Probing 127.0.0.11 at start
# proved unreliable on the hosting provider, so decide by platform instead: every pod has
# KUBERNETES_SERVICE_HOST set, compose containers never do. NGINX_RESOLVER
# overrides the decision.
set -eu
if [ -n "${NGINX_RESOLVER:-}" ]; then
  resolver="$NGINX_RESOLVER"
elif [ -n "${KUBERNETES_SERVICE_HOST:-}" ]; then
  resolver="$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)"
  : "${resolver:=127.0.0.11}"
else
  resolver="127.0.0.11"
fi
sed -i "s|__RESOLVER__|${resolver}|" /etc/nginx/conf.d/default.conf
echo "cividash-web: nginx resolver set to ${resolver} (nameservers: $(awk '/^nameserver/ { printf "%s ", $2 }' /etc/resolv.conf))"
