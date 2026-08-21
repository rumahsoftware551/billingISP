<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TenantSequenceService
{
    public function next(string $tenantId, string $key, string $prefix, int $digits = 6): string
    {
        return DB::transaction(function () use ($tenantId, $key, $prefix, $digits) {
            DB::table('tenant_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'key' => $key,
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $next = ((int) $row->value) + 1;

            DB::table('tenant_sequences')
                ->where('id', $row->id)
                ->update(['value' => $next, 'updated_at' => now()]);

            return $prefix.str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
        }, 3);
    }
}
