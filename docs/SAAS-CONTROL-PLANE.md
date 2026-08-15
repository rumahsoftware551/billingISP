# SaaS Control Plane

## Platform admin

User dengan `is_platform_admin=true` dapat membuka `/platform` tanpa bergantung pada tenant aktif. Control plane dapat membuat tenant baru, menentukan paket, mengubah status subscription, dan suspend/reactivate tenant.

## Paket dan limit

`platform_plans` menyimpan batas `max_customers`, `max_services`, `max_routers`, dan `max_users`. Nilai `NULL` berarti unlimited. `EnforcePlanLimit` memblokir create customer/service/router ketika limit tercapai.

## Subscription lifecycle

- `trialing`: aktif sampai `trial_ends_at`.
- `active`: akses normal.
- `past_due`: dapat tetap aktif hanya selama `grace_ends_at` masih berlaku dan periode belum dianggap selesai.
- `suspended`: tenant UI diblokir.
- `canceled`: tenant UI diblokir.

Customer Portal juga memeriksa subscription tenant sehingga portal tidak tetap terbuka ketika subscription SaaS ISP dinonaktifkan.

## Provision tenant

Platform admin membuat tenant dengan nama, slug, owner, password awal minimal 12 karakter, dan paket. Sistem membuat role Owner, membership, subscription trial 14 hari, dan platform event secara atomik dalam database transaction.
