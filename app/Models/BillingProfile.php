<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BillingProfile extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'auto_suspend' => 'boolean',
            'auto_reactivate' => 'boolean',
            'disconnect_on_suspend' => 'boolean',
        ];
    }
}
