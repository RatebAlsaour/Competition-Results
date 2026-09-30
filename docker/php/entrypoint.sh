#!/usr/bin/env sh
set -e

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    public/data \
    bootstrap/cache

chown -R www-data:www-data storage public/data bootstrap/cache || true

if [ "${CLEAR_CACHE_ON_BOOT:-true}" = "true" ]; then
    php artisan config:clear --no-interaction || true
    php artisan route:clear --no-interaction || true
    php artisan view:clear --no-interaction || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

if [ "${OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
    php artisan optimize --no-interaction || true
fi

# إعادة توليد ملفات النتائج الثابتة (public/data) للمسابقات المنشورة
if [ "${BUILD_STATIC_ON_BOOT:-true}" = "true" ]; then
    su -s /bin/sh www-data -c "php artisan results:build-static --no-interaction" || true
fi

exec "$@"
