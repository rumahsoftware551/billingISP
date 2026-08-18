#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

EXPECTED_VERSION="1.3.0-rc1"
EXPECTED_CHANNEL="release-candidate"

fail() {
  echo "FAIL: $*" >&2
  exit 1
}

[ -f VERSION.txt ] || fail "VERSION.txt missing"
[ "$(tr -d '\r\n' < VERSION.txt)" = "$EXPECTED_VERSION" ] || fail "VERSION.txt must be $EXPECTED_VERSION"

grep -q '"product_version"[[:space:]]*:[[:space:]]*"1.3.0-rc1"' PHASE.json || fail "PHASE.json product_version mismatch"
grep -q '"release_channel"[[:space:]]*:[[:space:]]*"release-candidate"' PHASE.json || fail "PHASE.json release_channel mismatch"

[ -f composer.lock ] || fail "composer.lock missing"
[ -f package-lock.json ] || fail "package-lock.json missing"
[ -f docker-compose.prod.yml ] || fail "docker-compose.prod.yml missing"
[ -f .env.production.example ] || fail ".env.production.example missing"

grep -q '^APP_ENV=production$' .env.production.example || fail "APP_ENV must be production"
grep -q '^APP_DEBUG=false$' .env.production.example || fail "APP_DEBUG must be false"
grep -q '^SESSION_SECURE_COOKIE=true$' .env.production.example || fail "SESSION_SECURE_COOKIE must be true"
grep -q '^FORCE_HTTPS=true$' .env.production.example || fail "FORCE_HTTPS must be true"
grep -q '^SEED_DEMO_DATA=false$' .env.production.example || fail "SEED_DEMO_DATA must be false"
grep -q '^JARINGANKU_VERSION=1.3.0-rc1$' .env.production.example || fail "JARINGANKU_VERSION mismatch"
grep -q '^RELEASE_CHANNEL=release-candidate$' .env.production.example || fail "RELEASE_CHANNEL mismatch"

if git ls-files --error-unmatch .env.production >/dev/null 2>&1; then
  fail ".env.production must never be tracked"
fi

tracked_secrets="$(git ls-files 'secrets/*.txt' | grep -v '\.example$' || true)"
[ -z "$tracked_secrets" ] || fail "real secret files are tracked: $tracked_secrets"

grep -q 'CHANGE_ME' .env.production.example || fail "production example should retain placeholders, not real secrets"
grep -q 'RADIUS_CLIENT_NETWORK=CHANGE_ME_' .env.production.example || fail "RADIUS CIDR must remain explicit placeholder in example"

grep -q 'jaringanku:phase15-security-audit --strict' scripts/prod-final-check.sh || fail "strict security production gate missing"
grep -q 'jaringanku:network-acceptance --strict' scripts/prod-final-check.sh || fail "strict network production gate missing"

echo "JARINGANKU V1.3 RELEASE CANDIDATE SOURCE GATE PASSED"