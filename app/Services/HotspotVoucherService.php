<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\HotspotProfile;
use App\Models\HotspotVoucher;
use App\Models\HotspotVoucherBatch;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotspotVoucherService
{
    private const CREDENTIAL_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(private readonly HotspotRadiusProjectionService $radius) {}

    public function generateBatch(
        HotspotProfile $profile,
        int $quantity,
        string $prefix,
        string $idempotencyKey,
        ?int $userId,
    ): HotspotVoucherBatch {
        $tenantId = app(CurrentTenant::class)->id();
        abort_unless((string) $profile->tenant_id === $tenantId, 404);

        return DB::transaction(function () use ($profile, $quantity, $prefix, $idempotencyKey, $userId, $tenantId): HotspotVoucherBatch {
            $existing = HotspotVoucherBatch::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ((int) $existing->hotspot_profile_id !== (int) $profile->id
                    || (int) $existing->quantity !== $quantity
                    || $existing->prefix !== $prefix) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Idempotency key sudah dipakai untuk batch voucher berbeda.',
                    ]);
                }
                $existing->setAttribute('idempotent_replay', true);
                return $existing;
            }

            /** @var HotspotProfile $lockedProfile */
            $lockedProfile = HotspotProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if (! $lockedProfile->active) {
                throw ValidationException::withMessages(['hotspot_profile_id' => 'Profil hotspot tidak aktif.']);
            }

            $batch = HotspotVoucherBatch::query()->create([
                'tenant_id' => $tenantId,
                'hotspot_profile_id' => $lockedProfile->id,
                'batch_code' => strtoupper($prefix).'-'.now()->format('ymd').'-'.$this->randomCredential(5),
                'prefix' => $prefix,
                'quantity' => $quantity,
                'idempotency_key' => $idempotencyKey,
                'status' => 'generated',
                'created_by' => $userId,
            ]);

            for ($index = 0; $index < $quantity; $index++) {
                $username = $this->uniqueUsername($prefix);
                HotspotVoucher::query()->create([
                    'tenant_id' => $tenantId,
                    'hotspot_voucher_batch_id' => $batch->id,
                    'hotspot_profile_id' => $lockedProfile->id,
                    'username' => $username,
                    'password' => $this->randomCredential(10),
                    'status' => 'available',
                ]);
            }

            return $batch;
        }, 3);
    }

    public function sell(
        HotspotVoucher $voucher,
        string $method,
        ?string $reference,
        string $idempotencyKey,
        ?int $userId,
    ): HotspotVoucher {
        $tenantId = app(CurrentTenant::class)->id();
        abort_unless((string) $voucher->tenant_id === $tenantId, 404);

        return DB::transaction(function () use ($voucher, $method, $reference, $idempotencyKey, $userId): HotspotVoucher {
            $existing = HotspotVoucher::query()
                ->where('sale_idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ((int) $existing->id !== (int) $voucher->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Idempotency key sudah dipakai untuk voucher berbeda.',
                    ]);
                }
                $existing->setAttribute('idempotent_replay', true);
                DB::afterCommit(fn () => $this->radius->syncVoucher($existing->fresh('profile')));
                return $existing;
            }

            /** @var HotspotVoucher $locked */
            $locked = HotspotVoucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'available') {
                throw ValidationException::withMessages(['voucher' => 'Voucher tidak lagi tersedia untuk dijual.']);
            }

            $locked->loadMissing('profile');
            $locked->forceFill([
                'status' => 'sold',
                'sale_idempotency_key' => $idempotencyKey,
                'sale_method' => $method,
                'sale_reference' => $reference,
                'sold_price' => $locked->profile->price,
                'sold_by' => $userId,
                'sold_at' => now(),
                'activation_deadline_at' => now()->addDays($locked->profile->activation_deadline_days),
            ])->save();

            DB::afterCommit(fn () => $this->radius->syncVoucher($locked->fresh('profile')));
            return $locked;
        }, 3);
    }

    public function disable(HotspotVoucher $voucher): HotspotVoucher
    {
        return DB::transaction(function () use ($voucher): HotspotVoucher {
            /** @var HotspotVoucher $locked */
            $locked = HotspotVoucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['sold', 'active'], true)) {
                throw ValidationException::withMessages(['voucher' => 'Hanya voucher terjual/aktif yang dapat dinonaktifkan.']);
            }
            $locked->forceFill(['status' => 'disabled', 'disabled_at' => now()])->save();
            DB::afterCommit(fn () => $this->radius->syncVoucher($locked->fresh('profile')));
            return $locked;
        }, 3);
    }

    public function enable(HotspotVoucher $voucher): HotspotVoucher
    {
        return DB::transaction(function () use ($voucher): HotspotVoucher {
            /** @var HotspotVoucher $locked */
            $locked = HotspotVoucher::query()->whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'disabled') {
                throw ValidationException::withMessages(['voucher' => 'Voucher tidak sedang dinonaktifkan.']);
            }
            if (($locked->expires_at && $locked->expires_at->isPast())
                || (! $locked->activated_at && $locked->activation_deadline_at?->isPast())) {
                throw ValidationException::withMessages(['voucher' => 'Voucher sudah kedaluwarsa dan tidak dapat diaktifkan kembali.']);
            }
            $locked->forceFill([
                'status' => $locked->activated_at ? 'active' : 'sold',
                'disabled_at' => null,
            ])->save();
            DB::afterCommit(fn () => $this->radius->syncVoucher($locked->fresh('profile')));
            return $locked;
        }, 3);
    }

    /** @return array{activated:int,expired:int,failed:int} */
    public function reconcileCurrentTenant(bool $resyncAll = false): array
    {
        $counts = ['activated' => 0, 'expired' => 0, 'failed' => 0];
        $now = now();
        $expiredIds = HotspotVoucher::query()
            ->where(function ($query) use ($now): void {
                $query->where(function ($active) use ($now): void {
                    $active->where('status', 'active')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', $now);
                })->orWhere(function ($sold) use ($now): void {
                    $sold->where('status', 'sold')
                        ->whereNull('activated_at')
                        ->whereNotNull('activation_deadline_at')
                        ->where('activation_deadline_at', '<=', $now);
                });
            })
            ->pluck('id')
            ->flip();

        HotspotVoucher::query()
            ->with('profile')
            ->whereIn('status', ['sold', 'active'])
            ->orderBy('id')
            ->chunkById(200, function ($vouchers) use (&$counts, $expiredIds, $now, $resyncAll): void {
                $starts = DB::table('radacct')
                    ->whereIn('username', $vouchers->pluck('username'))
                    ->whereNotNull('acctstarttime')
                    ->selectRaw('username, MIN(acctstarttime) AS first_seen_at')
                    ->groupBy('username')
                    ->pluck('first_seen_at', 'username');

                foreach ($vouchers as $voucher) {
                    try {
                        $changed = false;
                        if ($voucher->status === 'sold' && ! $voucher->activated_at && isset($starts[$voucher->username])) {
                            $activatedAt = CarbonImmutable::parse($starts[$voucher->username]);
                            $voucher->forceFill([
                                'status' => 'active',
                                'activated_at' => $activatedAt,
                                'expires_at' => $activatedAt->addMinutes($voucher->profile->validity_minutes),
                            ]);
                            $counts['activated']++;
                            $changed = true;
                        }

                        $expired = $expiredIds->has($voucher->id)
                            || ($changed && $voucher->expires_at?->lessThanOrEqualTo($now));
                        if ($expired) {
                            $voucher->forceFill(['status' => 'expired']);
                            $counts['expired']++;
                            $changed = true;
                        }

                        if ($changed) {
                            $voucher->save();
                        }
                        if ($changed || $resyncAll) {
                            $this->radius->syncVoucher($voucher->fresh('profile'));
                        }
                    } catch (\Throwable $e) {
                        report($e);
                        $counts['failed']++;
                    }
                }
            });

        return $counts;
    }

    private function uniqueUsername(string $prefix): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $username = strtolower($prefix).strtolower($this->randomCredential(8));
            $voucherExists = HotspotVoucher::withoutGlobalScope('tenant')->where('username', $username)->exists();
            $serviceExists = CustomerService::withoutGlobalScope('tenant')->where('pppoe_username', $username)->exists();
            if (! $voucherExists && ! $serviceExists) {
                return $username;
            }
        }

        throw new \RuntimeException('Gagal menghasilkan username voucher unik.');
    }

    private function randomCredential(int $length): string
    {
        $result = '';
        $max = strlen(self::CREDENTIAL_ALPHABET) - 1;
        for ($index = 0; $index < $length; $index++) {
            $result .= self::CREDENTIAL_ALPHABET[random_int(0, $max)];
        }
        return $result;
    }
}
