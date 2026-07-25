# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# base: shared PHP-FPM runtime with only the extensions this service needs
# (pdo_mysql, mbstring for Laravel, redis for cache/session). gd/exif/pcntl/
# bcmath were dropped — nothing in an auth service uses them.
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Pin Composer to a specific minor rather than a moving `latest`.
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# development: source and vendor are bind-mounted at runtime, and the process
# runs as the host user (UID/GID passed from the Makefile) so generated files
# stay owned by the developer. No vendor is baked in — the compose bind mount
# would only shadow it.
# ---------------------------------------------------------------------------
FROM base AS development

ARG UID=1000
ARG GID=1000

RUN (getent group ${GID} || groupadd -g ${GID} app) \
    && (getent passwd ${UID} || useradd -u ${UID} -g ${GID} -m -s /bin/bash app)

USER ${UID}:${GID}

# ---------------------------------------------------------------------------
# production: self-contained image with an authoritative, dev-free autoloader,
# code baked in, and no bind mounts. Runs as the unprivileged www-data user.
# ---------------------------------------------------------------------------
FROM base AS production

COPY www/ /var/www

RUN composer install \
        --no-interaction \
        --no-dev \
        --prefer-dist \
        --classmap-authoritative \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

USER www-data

# ---------------------------------------------------------------------------
# nginx-production: nginx needs its own copy of public/ because containers do
# not share the PHP image filesystem. Keeping it in an image avoids production
# source bind mounts while preserving try_files and static asset serving.
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx-production

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY www/public/ /var/www/public/
