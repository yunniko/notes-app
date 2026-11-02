# syntax=docker/dockerfile:1

# --- base: PHP-FPM with the MariaDB driver and Composer -------------------------------
FROM php:8.5-fpm-alpine AS base
RUN docker-php-ext-install pdo_mysql \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo "expose_php = Off" > "$PHP_INI_DIR/conf.d/zz-notes.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# --- dev: source is bind-mounted; used for tests, static analysis and formatting -------
FROM base AS dev
ENV COMPOSER_ALLOW_SUPERUSER=1
CMD ["composer", "check"]

# --- prod: dependencies baked in, no dev tools, runs as www-data ------------------------
FROM base AS prod
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --classmap-authoritative --no-scripts --no-autoloader
COPY bin ./bin
COPY migrations ./migrations
COPY public ./public
COPY src ./src
RUN composer dump-autoload --no-dev --classmap-authoritative \
 && rm /usr/bin/composer
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/notes-entrypoint
USER www-data
ENTRYPOINT ["notes-entrypoint"]
CMD ["php-fpm"]
