#!/usr/bin/env bash
    set -euo pipefail
    [ -f .env ] || cp .env.example .env
    if grep -q '^APP_KEY=$' .env; then
      KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
      sed -i "s|^APP_KEY=$|APP_KEY=${KEY}|" .env
    fi
    echo "Edit .env dan ganti semua nilai CHANGE_ME sebelum production."
    echo "Kemudian: docker compose up -d --build"
