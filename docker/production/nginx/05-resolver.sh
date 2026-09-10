#!/bin/sh
# Fill the nginx `resolver` directive at container start. Docker compose stacks
# resolve service names via the embedded DNS at 127.0.0.11; on Kubernetes the
# cluster DNS address differs per cluster, but is always the nameserver in
# /etc/resolv.conf. NGINX_RESOLVER overrides both.
set -eu
resolver="${NGINX_RESOLVER:-$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)}"
: "${resolver:=127.0.0.11}"
sed -i "s|__RESOLVER__|${resolver}|" /etc/nginx/conf.d/default.conf
echo "cividash-web: nginx resolver set to ${resolver}"
