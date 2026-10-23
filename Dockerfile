# syntax=docker/dockerfile:1

# Production images:
#   app  — PHP-FPM on Alpine, application code and Composer dependencies
#   web  — nginx on Alpine, public assets only
#   ssr  — Node on Alpine, the bundled Inertia SSR server

ARG PHP_VERSION=8.5
ARG NODE_VERSION=22

FROM composer:2 AS composer_bin

FROM php:${PHP_VERSION}-cli-bookworm AS build

ARG VITE_APP_NAME=Laravel

ENV VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_SSR_NO_EXTERNAL=1 \
    APP_ENV=local \
    APP_DEBUG=true \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    LOG_CHANNEL=stderr \
    BROADCAST_CONNECTION=log \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=array

COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates git libatomic1 unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=node:${NODE_VERSION}-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:${NODE_VERSION}-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /app

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --no-autoloader

COPY package.json package-lock.json ./

RUN --mount=type=cache,target=/root/.npm \
    npm ci

COPY . .

RUN export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')" \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && npm run build:ssr \
    && rm -rf node_modules

FROM php:${PHP_VERSION}-fpm-alpine AS app

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apk add --no-cache su-exec \
    && install-php-extensions intl opcache pcntl pdo_mysql \
    && rm -rf /tmp/* /var/cache/apk/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --chown=www-data:www-data artisan composer.json composer.lock ./
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data lang ./lang
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources/views ./resources/views
COPY --chown=www-data:www-data routes ./routes
COPY --from=build --chown=www-data:www-data /app/vendor ./vendor
COPY --from=build --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.28-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /var/www/html/public

FROM node:${NODE_VERSION}-alpine AS ssr

WORKDIR /app

COPY --from=build --chown=node:node /app/bootstrap/ssr ./bootstrap/ssr

USER node

ENV NODE_ENV=production

EXPOSE 13714

CMD ["node", "bootstrap/ssr/ssr.js"]
