<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\HotspotVoucher;
use Illuminate\Support\Facades\DB;
use LogicException;

class HotspotRadiusProjectionService
{
    public function syncVoucher(HotspotVoucher $voucher): void
    {
        $voucher->loadMissing('profile');

        if ($this->shouldRemove($voucher)) {
            $this->removeUsername($voucher->username);
            $this->touch($voucher);
            return;
        }

        if ($this->shouldReject($voucher)) {
            $this->projectReject($voucher);
            $this->touch($voucher);
            return;
        }

        if (CustomerService::withoutGlobalScope('tenant')->where('pppoe_username', $voucher->username)->exists()) {
            throw new LogicException('Username voucher bertabrakan dengan layanan PPPoE.');
        }

        DB::transaction(function () use ($voucher): void {
            $this->clearProjection($voucher->username);

            $checks = [
                ['attribute' => 'Cleartext-Password', 'value' => $voucher->password],
                ['attribute' => 'Simultaneous-Use', 'value' => (string) max(1, (int) $voucher->profile->simultaneous_use)],
            ];
            $expiry = $voucher->activated_at ? $voucher->expires_at : $voucher->activation_deadline_at;
            if ($expiry) {
                $checks[] = ['attribute' => 'Expiration', 'value' => $expiry->format('d M Y H:i:s')];
            }

            foreach ($checks as $check) {
                DB::table('radcheck')->insert([
                    'username' => $voucher->username,
                    'attribute' => $check['attribute'],
                    'op' => ':=',
                    'value' => $check['value'],
                ]);
            }

            $sessionSeconds = min(
                max(60, (int) $voucher->profile->session_timeout_minutes * 60),
                max(60, (int) $voucher->profile->validity_minutes * 60),
            );
            $replies = [
                'Mikrotik-Rate-Limit' => $voucher->profile->rate_limit,
                'Session-Timeout' => $sessionSeconds,
                'Idle-Timeout' => max(60, (int) $voucher->profile->idle_timeout_minutes * 60),
                'Acct-Interim-Interval' => 300,
                'Class' => 'jaringanku-voucher-'.$voucher->id,
            ];

            foreach ($replies as $attribute => $value) {
                DB::table('radreply')->insert([
                    'username' => $voucher->username,
                    'attribute' => $attribute,
                    'op' => ':=',
                    'value' => (string) $value,
                ]);
            }
        });

        $this->touch($voucher);
    }

    public function removeUsername(string $username): void
    {
        DB::transaction(fn () => $this->clearProjection($username));
    }

    private function projectReject(HotspotVoucher $voucher): void
    {
        DB::transaction(function () use ($voucher): void {
            $this->clearProjection($voucher->username);
            DB::table('radcheck')->insert([
                'username' => $voucher->username,
                'attribute' => 'Auth-Type',
                'op' => ':=',
                'value' => 'Reject',
            ]);
        });
    }

    private function clearProjection(string $username): void
    {
        DB::table('radcheck')->where('username', $username)->delete();
        DB::table('radreply')->where('username', $username)->delete();
        DB::table('radusergroup')->where('username', $username)->delete();
    }

    private function shouldRemove(HotspotVoucher $voucher): bool
    {
        return $voucher->status === 'available';
    }

    private function shouldReject(HotspotVoucher $voucher): bool
    {
        if (in_array($voucher->status, ['disabled', 'expired'], true)) {
            return true;
        }

        if ($voucher->status === 'sold' && $voucher->activation_deadline_at?->isPast()) {
            return true;
        }

        return $voucher->status === 'active' && $voucher->expires_at?->isPast();
    }

    private function touch(HotspotVoucher $voucher): void
    {
        $voucher->forceFill(['last_radius_sync_at' => now()])->saveQuietly();
    }
}
