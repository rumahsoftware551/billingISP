<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InternetPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'price', 'download_kbps', 'upload_kbps',
        'active', 'radius_attributes', 'acct_interim_interval',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'radius_attributes' => 'array',
            'acct_interim_interval' => 'integer',
        ];
    }

    public function services()
    {
        return $this->hasMany(CustomerService::class);
    }
}

