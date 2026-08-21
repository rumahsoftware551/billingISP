# Jaringanku Phase 09 FULL V1 — Release Notes

## Added

- Midtrans Snap payment adapter
- local-only mock QRIS provider
- payment gateway transaction/event persistence
- verified/idempotent Midtrans settlement callback
- WhatsApp Cloud API + local LOG adapter
- Meta webhook status ingestion
- invoice/payment/overdue WhatsApp notifications
- tenant Integration settings page
- Phase 09 scheduler jobs and smoke acceptance gate

## Security / correctness

- gateway and WhatsApp secrets use encrypted model casts
- mock payment and LOG WhatsApp are local-only
- callback validates signature and amount before posting payment
- payment posting uses the existing transactional PaymentService
- duplicate callbacks cannot create a second payment for an already-paid gateway transaction
- smoke test disables tenant business webhooks temporarily to avoid dispatching synthetic smoke events externally
