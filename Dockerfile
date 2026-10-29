# بدون "# syntax=": يعمل البناء بدون إنترنت باستخدام الصور الموجودة على السيرفر

FROM php:8.3-fpm-bookworm AS php-base

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        default-mysql-client \
        libicu-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-competition-results.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-competition-results.conf

FROM php-base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

FROM node:20-bookworm-slim AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm run build \
    && test -f public/build/manifest.json

FROM php-base AS app

# امتداد Redis اختياري: يحتاج إنترنت أثناء البناء (pecl). اتركه false إن كان السيرفر بلا إنترنت،
# والنظام يعمل عندها بكاش قاعدة البيانات. لتفعيله انظر deploy.md (قسم الكاش).
ARG INSTALL_REDIS=false
RUN if [ "$INSTALL_REDIS" = "true" ]; then \
        apt-get update \
        && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
        && pecl install redis \
        && docker-php-ext-enable redis \
        && apt-get purge -y --auto-remove $PHPIZE_DEPS \
        && rm -rf /tmp/pear /var/lib/apt/lists/*; \
    fi

ARG APP_BUILD_BRANCH=
ARG APP_BUILD_COMMIT=
ARG APP_BUILD_COMMIT_DATE=

ENV APP_BUILD_BRANCH="${APP_BUILD_BRANCH}" \
    APP_BUILD_COMMIT="${APP_BUILD_COMMIT}" \
    APP_BUILD_COMMIT_DATE="${APP_BUILD_COMMIT_DATE}"

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /var/www/html/public/build ./public/build
COPY docker/php/entrypoint.sh /usr/local/bin/competition-results-entrypoint

RUN chmod +x /usr/local/bin/competition-results-entrypoint \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-*.php \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        public/data \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

ENTRYPOINT ["competition-results-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/app-fastcgi.conf /etc/nginx/snippets/app-fastcgi.conf
COPY --from=app /var/www/html/public ./public

RUN mkdir -p /var/www/html/public/data
