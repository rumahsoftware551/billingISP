# Jaringanku V1.3.0 RC1 â€” Release Notes

## Status
Release Candidate untuk pilot production. Belum boleh dipromosikan menjadi stable sebelum acceptance perangkat nyata selesai.

## Cumulative changes
- Phase 01: reproducible dependency locking, automated tests, GitHub Actions, security gates.
- Phase 02: professional permission-aware UI/UX, mobile polish, portal/login cleanup, UI regression gate.
- Phase 03: recurring billing, billing calendar, grace period, payment reconciliation, invoice/receipt PDF, commercial finance gate.
- Phase 04: MikroTik/RADIUS hardening, network monitoring, CoA/disconnect safety, projection integrity, network acceptance gate.
- Phase 05: production release metadata, secret/configuration checks, backup/TLS/readiness/live-RADIUS acceptance tooling.

## RC acceptance required before Stable
1. GitHub Actions release branch must be green.
2. Deploy RC1 to VPS/pilot.
3. `ops/07-v13-live-acceptance.sh` must PASS.
4. Test 3â€“10 pilot customers end-to-end:
   - PPPoE Access-Accept and accounting.
   - Invoice generation.
   - Overdue/grace policy.
   - Suspend + CoA/disconnect.
   - Payment posting/reconciliation.
   - Reactivation and reconnect.
   - Customer/Mitra/Inventory portal access.
5. Create backup and verify checksum.
6. Restore backup on non-production environment.
7. Only then promote `v1.3.0` stable.