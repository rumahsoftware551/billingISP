# Jaringanku Phase 10 — Docker Compose Production Deployment

Phase 10 retains the hardened production Compose baseline and adds the customer self-service portal on top of the Phase 09 payment/WhatsApp integrations. It is not the final Phase 12 SaaS release, but it is suitable for controlled staging / pre-production testing.

## 1. Initialize production files

On Ubuntu/Linux:

```bash
chmod +x scripts/*.sh
./scripts/prod-init.sh
```

This creates `.env.production` and secret files under `secrets/` with restrictive permissions.

## 2. Edit `.env.production`

At minimum configure:

```env
APP_URL=https://billing.example.com
APP_BIND=127.0.0.1
APP_PORT=8080
TRUSTED_PROXIES=*
RADIUS_CLIENT_NETWORK=<mikrotik-ip>/32
SEED_ADMIN_EMAIL=<admin-email>
MAIL_MAILER=log
```

Keep `APP_DEBUG=false`, `FORCE_HTTPS=true`, `SESSION_ENCRYPT=true`, and `SESSION_SECURE_COOKIE=true`. `HEALTH_TOKEN`, `APP_KEY`, database password, RADIUS secret, and initial admin password are read from the generated files in `secrets/`; do not duplicate those secret values into `.env.production`.

`TRUSTED_PROXIES=*` is appropriate only for this Compose layout where PHP-FPM is not published and the Compose Nginx port is bound to loopback behind a trusted host TLS reverse proxy. If you change that topology, restrict trusted proxies explicitly.

## 3. Reverse proxy / TLS

The production Compose Nginx binds to `127.0.0.1:8080` by default. Put a host-level TLS reverse proxy in front of it and forward:

```text
X-Forwarded-Proto
X-Forwarded-Host
X-Forwarded-For
```

Do not expose PHP-FPM, PostgreSQL or Redis to the Internet.

## 4. Start

```bash
./scripts/prod-up.sh
```

Then inspect:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
docker compose --env-file .env.production -f docker-compose.prod.yml logs --tail=100 app radius queue scheduler nginx backup
```

## 5. Initial seed

The production seeder is idempotent and `SEED_DEMO_DATA=false` prevents demo PPPoE data. Run once after verifying your `SEED_ADMIN_*` values:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app jaringanku-cli php artisan db:seed --force
```

Change the initial admin password after first login when account-management UI is added/finalized in later phases.


## Payment & WhatsApp integration endpoints

After HTTPS is live, configure external providers with:

```text
Midtrans notification: https://YOUR-DOMAIN/api/payments/midtrans/notification
WhatsApp webhook:      https://YOUR-DOMAIN/api/whatsapp/{tenant-slug}/webhook
```

Configure tenant credentials from `/integrations`. Do not enable local `mock` payment or WhatsApp `log` mode in production; the application rejects those modes outside the local environment.

## 6. Backup

Automatic backup service runs continuously. Manual backup:

```bash
./scripts/prod-backup.sh
```

## 7. Restore

Restore is destructive and intentionally requires typing `RESTORE`:

```bash
./scripts/prod-restore.sh backups/jaringanku-manual-YYYYMMDDTHHMMSSZ.dump
```

## 8. Deploy update

Before an update, create a backup. Then build/up. Laravel's production entrypoint runs migrations and `php artisan optimize`. Queue workers are recycled using `php artisan queue:restart` from `prod-up.sh`.

## 9. Firewall

Expose only what is required:

- TCP 80/443 to the host TLS reverse proxy
- UDP 1812/1813 only from known MikroTik/NAS source addresses where possible
- UDP 3799 is an inbound port on MikroTik for CoA/Disconnect; it is not a Jaringanku server port
- PostgreSQL 5432 and Redis 6379 stay internal to Docker
