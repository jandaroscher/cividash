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

# Pull public/ from the application image (tag must already exist locally).
FROM cividash-app:dev AS appsrc

FROM nginx:alpine

# Laravel front controller + compiled assets, served straight from disk.
COPY --from=appsrc /var/www/html/public /var/www/html/public

# Server config: try_files + fastcgi_pass cividash-fpm:9000.
COPY docker/production/nginx/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
