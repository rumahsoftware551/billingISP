<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotVoucherBatch extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function profile()
    {
        return $this->belongsTo(HotspotProfile::class, 'hotspot_profile_id');
    }

    public function vouchers()
    {
        return $this->hasMany(HotspotVoucher::class);
    }
}
