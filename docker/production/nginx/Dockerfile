# syntax=docker/dockerfile:1
#
# CiviDash web image (cividash-web).
#
# A thin nginx front for the Laravel public/ directory. It serves static assets
# directly and proxies PHP requests to the cividash-fpm service over fastcgi.
#
# It reuses the already-built cividash-app image purely as a file source for the
# public/ tree (compiled assets, front controller). Build cividash-app FIRST and
# tag it cividash-app:dev, then build this image with the repo root as context:
#   docker build -f docker/production/Dockerfile        -t cividash-app:dev .
#   docker build -f docker/production/nginx/Dockerfile  -t cividash-web:dev .
#
# External pipelines may pass --build-arg APP_IMAGE=... so cividash-web copies
# public/ from the exact same app image. The smoke stack
# (docker-compose.smoke.yml) maps the cividash-app:dev default below to the
# cividash-fpm service build via additional_contexts.
ARG APP_IMAGE=cividash-app:dev

# Pull public/ from the application image (tag must already exist locally or in
# the registry, unless the build maps it to another context).
FROM ${APP_IMAGE} AS appsrc

FROM nginx:alpine

# Laravel front controller + compiled assets, served straight from disk.
COPY --from=appsrc /var/www/html/public /var/www/html/public

# Server config: try_files + fastcgi_pass cividash-fpm:9000. The resolver line is
# filled in at start (see 05-resolver.sh) so the same image works in compose
# (127.0.0.11) and on Kubernetes (cluster DNS).
COPY docker/production/nginx/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/production/nginx/05-resolver.sh /docker-entrypoint.d/05-resolver.sh
RUN chmod +x /docker-entrypoint.d/05-resolver.sh

EXPOSE 80
