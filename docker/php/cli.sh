#!/usr/bin/env sh
set -eu

load_secret() {
  var="$1"
  file_var="${var}_FILE"
  eval "file_value=\${$file_var:-}"
  eval "current_value=\${$var:-}"
  if [ -n "$file_value" ]; then
    [ -r "$file_value" ] || { echo "Secret file for $var is not readable: $file_value" >&2; exit 1; }
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
exec "$@"
