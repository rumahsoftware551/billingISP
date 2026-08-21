<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class IpPool extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'start_ip', 'end_ip', 'gateway', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
