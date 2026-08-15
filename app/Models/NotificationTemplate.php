<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'name', 'channel', 'subject', 'body', 'variables', 'enabled'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'enabled' => 'boolean'];
    }
}
