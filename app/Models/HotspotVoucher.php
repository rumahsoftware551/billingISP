<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class HotspotVoucher extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'hotspot_voucher_batch_id', 'hotspot_profile_id', 'username',
        'password', 'status', 'sale_idempotency_key', 'sale_method', 'sale_reference',
        'sold_price', 'sold_by', 'sold_at', 'activation_deadline_at', 'activated_at',
        'expires_at', 'disabled_at', 'last_radius_sync_at',
    ];

    protected $hidden = ['password_encrypted', 'password'];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'activation_deadline_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_radius_sync_at' => 'datetime',
        ];
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_encrypted'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute(): string
    {
        return Crypt::decryptString($this->password_encrypted);
    }

    public function batch()
    {
        return $this->belongsTo(HotspotVoucherBatch::class, 'hotspot_voucher_batch_id');
    }

    public function profile()
    {
        return $this->belongsTo(HotspotProfile::class, 'hotspot_profile_id');
    }
}
