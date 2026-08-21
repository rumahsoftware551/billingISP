<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'type', 'severity', 'ip_address', 'user_agent', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
