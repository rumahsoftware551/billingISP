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

for secret_name in DB_PASSWORD RADIUS_SHARED_SECRET; do
    load_secret "$secret_name"
done

escape_sed() {
    printf '%s' "$1" | sed 's/[&|\\]/\\&/g'
}

DB_HOST_E=$(escape_sed "${DB_HOST:-postgres}")
DB_PORT_E=$(escape_sed "${DB_PORT:-5432}")
DB_USER_E=$(escape_sed "${DB_USERNAME:-jaringanku}")
DB_NAME_E=$(escape_sed "${DB_DATABASE:-jaringanku}")
DB_PASS_E=$(escape_sed "${DB_PASSWORD:?DB_PASSWORD is required}")
SECRET_E=$(escape_sed "${RADIUS_SHARED_SECRET:?RADIUS_SHARED_SECRET is required}")

# Docker Compose may allocate a different subnet on every machine.  Resolve the
# Laravel app service at runtime and authorize only that exact container IP for
# the built-in radtest smoke test.  This avoids fixed-subnet collisions.
APP_IP=""
i=0
while [ "$i" -lt 30 ]; do
    APP_IP=$(getent hosts app 2>/dev/null | awk 'NR==1 {print $1}' || true)
    if [ -n "$APP_IP" ]; then
        break
    fi
    i=$((i + 1))
    sleep 1
done

if [ -z "$APP_IP" ]; then
    echo "ERROR: unable to resolve Docker service 'app' for RADIUS internal client" >&2
    exit 1
fi

case "$APP_IP" in
    *:*) DOCKER_CLIENT="${APP_IP}/128" ;;
    *)   DOCKER_CLIENT="${APP_IP}/32" ;;
esac
DOCKER_NET_E=$(escape_sed "$DOCKER_CLIENT")

sed \
  -e "s|__DB_HOST__|${DB_HOST_E}|g" \
  -e "s|__DB_PORT__|${DB_PORT_E}|g" \
  -e "s|__DB_USER__|${DB_USER_E}|g" \
  -e "s|__DB_NAME__|${DB_NAME_E}|g" \
  -e "s|__DB_PASSWORD__|${DB_PASS_E}|g" \
  /opt/jaringanku/sql.template > /etc/raddb/mods-available/sql

sed \
  -e "s|__RADIUS_SECRET__|${SECRET_E}|g" \
  -e "s|__DOCKER_CLIENT_NETWORK__|${DOCKER_NET_E}|g" \
  /opt/jaringanku/clients.base.conf > /etc/raddb/clients.conf

# Optional external NAS client for a real MikroTik/NAS.  Local smoke tests do
# not need this client because localhost and the Laravel app container are
# already registered above.  Older Phase 01/02 local .env files may contain
# 127.0.0.1/32; adding that again would be a duplicate of localhost_jaringanku
# and FreeRADIUS would refuse to start.
EXTERNAL_NET="${RADIUS_CLIENT_NETWORK:-}"
case "$EXTERNAL_NET" in
    ""|disabled|DISABLED|localhost|127.*|::1|::1/*|CHANGE_ME*|change_me*)
        EXTERNAL_NET=""
        ;;
esac

# Also skip an exact duplicate of the dynamically resolved app client.
if [ "$EXTERNAL_NET" = "$DOCKER_CLIENT" ] || [ "$EXTERNAL_NET" = "$APP_IP" ]; then
    EXTERNAL_NET=""
fi

if [ -n "$EXTERNAL_NET" ]; then
    cat >> /etc/raddb/clients.conf <<EOF

client external_nas_env {
    ipaddr = ${EXTERNAL_NET}
    secret = "${RADIUS_SHARED_SECRET}"
    require_message_authenticator = auto
}
EOF
fi

ln -sf /etc/raddb/mods-available/sql /etc/raddb/mods-enabled/sql

# Fail fast with a useful log if configuration or the SQL module is invalid.
freeradius -XC

exec "$@"
