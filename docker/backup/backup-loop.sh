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

load_secret DB_PASSWORD
export PGPASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-jaringanku}"
DB_USERNAME="${DB_USERNAME:-jaringanku}"
RETENTION="${BACKUP_RETENTION_DAYS:-14}"
INTERVAL="${BACKUP_INTERVAL_SECONDS:-86400}"
mkdir -p /backups

while true; do
  stamp=$(date -u +%Y%m%dT%H%M%SZ)
  target="/backups/jaringanku-${stamp}.dump"
  temp="${target}.tmp"
  echo "[$(date -u +%FT%TZ)] Starting PostgreSQL backup to ${target}"
  if pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc -f "$temp"; then
    mv "$temp" "$target"
    sha256sum "$target" > "${target}.sha256"
    echo "[$(date -u +%FT%TZ)] Backup complete: ${target}"
  else
    rm -f "$temp"
    echo "[$(date -u +%FT%TZ)] Backup failed" >&2
  fi
  find /backups -type f \( -name 'jaringanku-*.dump' -o -name 'jaringanku-*.dump.sha256' \) -mtime "+$RETENTION" -delete || true
  sleep "$INTERVAL"
done
