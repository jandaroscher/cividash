# syntax=docker/dockerfile:1
#
# CiviDash application image (cividash-app).
#
# Multi-stage build:
#   1. assets  — node:22-alpine  -> compiles the Vue/Tailwind frontend (public/build)
#   2. vendor  — composer:2       -> installs production PHP deps + optimized autoloader
#   3. runtime — php:8.2-fpm-alpine -> final image: app code + vendor + built assets
#
# The image runs PHP-FPM on tcp 9000 as the non-root www-data user. It is the
# single source workload for: fpm (php-fpm), queue (queue:work), scheduler
# (schedule:run loop) and migrate (migrate --force). The sibling cividash-web image
# (docker/production/nginx/Dockerfile) copies public/ from this image.
#
# Build context MUST be the repository root, e.g.:
#   docker build -f docker/production/Dockerfile -t cividash-app:dev .

# ---------------------------------------------------------------------------
# Stage 1: assets — build the frontend bundle with Vite into public/build.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

# Install JS deps from the lockfile first so this layer caches across source
# edits. npm ci requires both package.json and package-lock.json.
COPY package.json package-lock.json ./
RUN npm ci

# Copy only the inputs Vite needs to produce public/build.
COPY vite.config.js ./
COPY resources ./resources
# public/ is the Vite outDir parent; copying it keeps any committed static
# assets (favicon, robots.txt, etc.) referenced during the build.
COPY public ./public

# Produces public/build (manifest.json + hashed assets).
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: vendor — install production-only Composer dependencies.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Install deps from the lockfile without running package scripts (artisan is
# not yet available / no app code present). --no-dev keeps dev tooling out.
# --ignore-platform-reqs: the composer:2 image's PHP lacks some runtime
# extensions (ext-intl, ext-gd, ...), but composer.lock pins exact versions and
# the php:8.2-fpm-alpine runtime stage installs every required extension, so we
# only need to assemble vendor/ here, not satisfy platform reqs.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
        --no-interaction \
        --no-progress \
        --ignore-platform-reqs

# Bring in the rest of the application, then regenerate an optimized,
# production-only classmap now that all source is present.
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 3: runtime — the final PHP-FPM image.
# ---------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS runtime

WORKDIR /var/www/html

# --- PHP extensions -------------------------------------------------------
# Runtime libs are kept; build-only deps are installed in a virtual package
# and dropped in the same layer to keep the image small.
#   pdo_mysql  -> MariaDB/MySQL driver
#   pdo_pgsql  -> PostgreSQL driver
#   mbstring   -> Laravel string handling
#   bcmath     -> precise numeric ops (Filament, money)
#   intl       -> localization (de/en)
#   zip        -> Composer/asset extraction, exports
#   gd         -> image processing (freetype + jpeg)
#   exif       -> image metadata
#   opcache    -> bytecode cache (perf)
#   pcntl      -> queue:work / scheduler signal handling
# postgresql-client / mariadb-client: `artisan migrate` loads database/schema/*-schema.sql
# through psql / mysql on a fresh database (schema dump instead of 85 migrations).
RUN set -eux; \
    apk add --no-cache \
        fcgi \
        freetype \
        libjpeg-turbo \
        libpng \
        libzip \
        icu-libs \
        oniguruma \
        libpq \
        postgresql-client \
        mariadb-client; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        postgresql-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        exif \
        opcache \
        pcntl; \
    apk del .build-deps

# --- PHP / FPM configuration ---------------------------------------------
COPY docker/production/php/opcache.ini  /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/production/php/php.ini       /usr/local/etc/php/conf.d/zz-cividash.ini
COPY docker/production/php/www.conf      /usr/local/etc/php-fpm.d/zz-cividash.conf

# --- Application code -----------------------------------------------------
# Production vendor + optimized autoloader and full app source from the vendor
# stage, then overlay the freshly built frontend assets from the assets stage.
COPY --from=vendor --chown=www-data:www-data /app                  /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build     /var/www/html/public/build

# --- Filament assets / upgrade (DB-free, no APP_KEY required) -------------
# filament:assets publishes JS/CSS into public/. filament:upgrade runs the
# package upgrade hooks. Neither touches the database. We disable Laravel's
# bootstrap cache discovery side effects by keeping this isolated; if either
# command misbehaves at build time it must fail loudly, so no `|| true`.
RUN php artisan filament:assets \
    && php artisan filament:upgrade \
    && php artisan storage:link

# --- Entrypoint -----------------------------------------------------------
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# --- Writable dirs / ownership -------------------------------------------
# Ensure the Laravel writable trees exist and are owned by the runtime user.
# bootstrap/cache/filament is where filament:cache-components writes.
RUN set -eux; \
    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache/filament; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

# Drop privileges: PHP-FPM master + workers run as www-data.
USER www-data

EXPOSE 9000

# Optional liveness probe against the FPM ping endpoint (configured in www.conf).
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
