#!/usr/bin/env sh
set -eu

load_secret() {
  var="$1"
  file_var="${var}_FILE"
  eval "file_value=\${$file_var:-}"
  eval "current_value=\${$var:-}"
  if [ -n "$file_value" ]; then
    if [ ! -r "$file_value" ]; then
      echo "Secret file for $var is not readable: $file_value" >&2
      exit 1
    fi
    value=$(cat "$file_value")
    export "$var=$value"
  elif [ -n "$current_value" ]; then
    export "$var=$current_value"
  fi
}

for secret_name in APP_KEY DB_PASSWORD RADIUS_SHARED_SECRET SEED_ADMIN_PASSWORD HEALTH_TOKEN; do
  load_secret "$secret_name"
done

cd /var/www/html

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

DB_HOST_VALUE="${DB_HOST:-postgres}"
DB_PORT_VALUE="${DB_PORT:-5432}"
DB_DATABASE_VALUE="${DB_DATABASE:-jaringanku}"
DB_USERNAME_VALUE="${DB_USERNAME:-jaringanku}"

attempt=0
until php -r '
$dsn = sprintf(
    "pgsql:host=%s;port=%s;dbname=%s",
    getenv("DB_HOST") ?: "postgres",
    getenv("DB_PORT") ?: "5432",
    getenv("DB_DATABASE") ?: "jaringanku"
);
try {
    new PDO($dsn, getenv("DB_USERNAME") ?: "jaringanku", getenv("DB_PASSWORD") ?: "");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
' >/dev/null 2>&1; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 60 ]; then
    echo "Database belum siap setelah 120 detik: ${DB_HOST_VALUE}:${DB_PORT_VALUE}/${DB_DATABASE_VALUE} user=${DB_USERNAME_VALUE}" >&2
    exit 1
  fi
  sleep 2
done

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
  php artisan storage:link --force >/dev/null 2>&1 || true
fi

if [ "${APP_ENV:-production}" = "production" ]; then
  php artisan optimize
else
  php artisan optimize:clear >/dev/null 2>&1 || true
fi

exec "$@"
