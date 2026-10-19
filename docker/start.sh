#!/bin/sh
set -e

cd /app

# Railway volumes mount empty; make sure Laravel's storage tree exists.
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:clear
php artisan storage:link --force
php artisan migrate --force
# Seeders use firstOrCreate, so re-running them on each deploy is safe.
php artisan db:seed --force
php artisan l5-swagger:generate

# فرح: أول حساب أدمن. عرّف ADMIN_EMAIL و ADMIN_PASSWORD (و ADMIN_NAME اختياري) في متغيرات Railway.
# لا يغيّر شيئًا إذا كان الحساب موجودًا، فتعديلاته من لوحة التحكم تبقى كما هي.
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan admin:create --if-missing \
        --name="${ADMIN_NAME:-مدير النظام}" \
        --email="$ADMIN_EMAIL" \
        --password="$ADMIN_PASSWORD" \
        --role=system.admin --no-interaction || true
fi

# Laravel scheduler (bookings:expire-stale every five minutes).
(
    while true; do
        php artisan schedule:run --no-interaction || true
        sleep 60
    done
) &

# PHP_CLI_SERVER_WORKERS lets artisan serve handle requests in parallel.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

exec php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-10000}"
