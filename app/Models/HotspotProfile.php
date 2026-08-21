<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'price', 'validity_minutes',
        'session_timeout_minutes', 'idle_timeout_minutes', 'simultaneous_use',
        'activation_deadline_days', 'rate_limit', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function batches()
    {
        return $this->hasMany(HotspotVoucherBatch::class);
    }

    public function vouchers()
    {
        return $this->hasMany(HotspotVoucher::class);
    }
}
