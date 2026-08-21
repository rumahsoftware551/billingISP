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
STORAGE_PATH="${BACKUP_STORAGE_PATH:-/storage}"

[ -d "$STORAGE_PATH" ] || { echo "Storage path tidak ditemukan: $STORAGE_PATH" >&2; exit 1; }
mkdir -p /backups

while true; do
  stamp=$(date -u +%Y%m%dT%H%M%SZ)
  database_target="/backups/jaringanku-${stamp}.dump"
  storage_target="/backups/jaringanku-${stamp}.storage.tar.gz"
  manifest_target="/backups/jaringanku-${stamp}.manifest.sha256"
  database_temp="${database_target}.tmp"
  storage_temp="${storage_target}.tmp"

  echo "[$(date -u +%FT%TZ)] Starting PostgreSQL and storage backup"
  if pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc -f "$database_temp" \
    && tar -C "$STORAGE_PATH" -czf "$storage_temp" .; then
    mv "$database_temp" "$database_target"
    mv "$storage_temp" "$storage_target"
    (
      cd /backups
      sha256sum "$(basename "$database_target")" "$(basename "$storage_target")"
    ) > "$manifest_target"
    echo "[$(date -u +%FT%TZ)] Backup complete: ${database_target}, ${storage_target}"
  else
    rm -f "$database_temp" "$storage_temp"
    echo "[$(date -u +%FT%TZ)] Backup failed" >&2
  fi
  find /backups -type f \( -name 'jaringanku-*.dump' -o -name 'jaringanku-*.storage.tar.gz' -o -name 'jaringanku-*.manifest.sha256' \) -mtime "+$RETENTION" -delete || true
  sleep "$INTERVAL"
done
