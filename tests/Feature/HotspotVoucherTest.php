<?php

namespace Tests\Feature;

use App\Models\HotspotProfile;
use App\Models\HotspotVoucher;
use App\Services\HotspotRadiusProjectionService;
use App\Services\HotspotVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotspotVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_generation_is_idempotent_and_encrypts_passwords_at_rest(): void
    {
        $tenant = $this->createTenant();
        $profile = $this->profile($tenant->id);
        $key = (string) Str::uuid();
        $service = app(HotspotVoucherService::class);

        $first = $service->generateBatch($profile, 3, 'VCR', $key, null);
        $second = $service->generateBatch($profile, 3, 'VCR', $key, null);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue((bool) $second->getAttribute('idempotent_replay'));
        $this->assertSame(3, HotspotVoucher::query()->count());
        $this->assertSame(3, HotspotVoucher::query()->distinct('username')->count('username'));

        $voucher = HotspotVoucher::query()->firstOrFail();
        $plainPassword = $voucher->password;
        $ciphertext = DB::table('hotspot_vouchers')->where('id', $voucher->id)->value('password_encrypted');
        $this->assertNotSame($plainPassword, $ciphertext);
        $this->assertStringNotContainsString($plainPassword, (string) $ciphertext);
        $this->assertDatabaseMissing('radcheck', ['username' => $voucher->username]);
    }

    public function test_sold_voucher_is_projected_activated_from_accounting_and_expired(): void
    {
        $tenant = $this->createTenant();
        $profile = $this->profile($tenant->id, validityMinutes: 60);
        $service = app(HotspotVoucherService::class);
        $radius = app(HotspotRadiusProjectionService::class);
        $batch = $service->generateBatch($profile, 1, 'DAY', (string) Str::uuid(), null);
        $voucher = $batch->vouchers()->firstOrFail();
        $password = $voucher->password;
        $saleKey = (string) Str::uuid();

        $sold = $service->sell($voucher, 'qris', 'QRIS-TEST-1', $saleKey, null);
        $replayed = $service->sell($voucher, 'qris', 'QRIS-TEST-1', $saleKey, null);
        $radius->syncVoucher($sold->fresh('profile'));

        $this->assertSame('sold', $sold->status);
        $this->assertSame($sold->id, $replayed->id);
        $this->assertTrue((bool) $replayed->getAttribute('idempotent_replay'));
        $this->assertDatabaseHas('radcheck', [
            'username' => $sold->username,
            'attribute' => 'Cleartext-Password',
            'value' => $password,
        ]);
        $this->assertDatabaseHas('radcheck', [
            'username' => $sold->username,
            'attribute' => 'Simultaneous-Use',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('radreply', [
            'username' => $sold->username,
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '2M/5M',
        ]);

        DB::table('radacct')->insert([
            'acctsessionid' => 'voucher-session-1',
            'acctuniqueid' => 'voucher-unique-1',
            'username' => $sold->username,
            'nasipaddress' => '192.0.2.10',
            'acctstarttime' => now(),
        ]);

        $result = $service->reconcileCurrentTenant();
        $active = $sold->fresh();
        $this->assertSame(1, $result['activated']);
        $this->assertSame('active', $active->status);
        $this->assertNotNull($active->activated_at);
        $this->assertNotNull($active->expires_at);

        $active->forceFill(['expires_at' => now()->subMinute()])->save();
        $result = $service->reconcileCurrentTenant();
        $this->assertSame(1, $result['expired']);
        $this->assertSame('expired', $active->fresh()->status);
        $this->assertDatabaseHas('radcheck', [
            'username' => $sold->username,
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ]);
        $this->assertDatabaseMissing('radcheck', [
            'username' => $sold->username,
            'attribute' => 'Cleartext-Password',
        ]);
    }

    private function profile(string $tenantId, int $validityMinutes = 1440): HotspotProfile
    {
        return HotspotProfile::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Voucher Test 5 Mbps',
            'code' => 'VCR-TEST',
            'price' => 5000,
            'validity_minutes' => $validityMinutes,
            'session_timeout_minutes' => min(480, $validityMinutes),
            'idle_timeout_minutes' => 5,
            'simultaneous_use' => 1,
            'activation_deadline_days' => 30,
            'rate_limit' => '2M/5M',
            'active' => true,
        ]);
    }
}
