<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Router extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'host', 'rest_port', 'api_username', 'api_password',
        'verify_tls', 'status', 'routeros_version', 'board_name', 'last_seen_at', 'last_error',
    ];

    protected $hidden = ['api_password_encrypted'];

    protected function casts(): array
    {
        return [
            'verify_tls' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function setApiPasswordAttribute(string $value): void
    {
        $this->attributes['api_password_encrypted'] = Crypt::encryptString($value);
    }

    public function getApiPasswordAttribute(): string
    {
        return Crypt::decryptString($this->api_password_encrypted);
    }

    public function nas()
    {
        return $this->hasMany(NetworkNas::class);
    }
}
