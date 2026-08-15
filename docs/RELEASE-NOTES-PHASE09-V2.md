# Jaringanku Phase 09 FULL V2 — Release Notes

## Fixed

- Fixed Eloquent table-name inference for `WhatsAppSetting`.
  - Laravel infers `WhatsAppSetting` as `whats_app_settings`.
  - Phase 09 schema intentionally uses `whatsapp_settings`.
  - The model now explicitly declares `protected $table = 'whatsapp_settings';`.
- Fixed Eloquent table-name inference for `WhatsAppMessageLog`.
  - The model now explicitly declares `protected $table = 'whatsapp_message_logs';`.
- Added `jaringanku:phase09-preflight` acceptance check before database seeding.
  - Verifies all five Phase 09 tables exist.
  - Verifies all five Phase 09 models resolve to the expected table names.
  - Prevents a seeder from running against an incorrectly inferred table name.

## Upgrade safety

- No Phase 09 migration needs to be rolled back.
- Existing Phase 01–08 data and Phase 09 tables remain valid.
- Named PostgreSQL, Redis, and storage volumes remain unchanged.
- `local-up.ps1` continues to avoid destructive `down -v` operations.
