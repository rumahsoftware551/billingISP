<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NetworkNas extends Model
{
    use BelongsToTenant;

    protected $table = 'network_nas';

    protected $fillable = [
        'tenant_id', 'router_id', 'nasname', 'shortname', 'type', 'secret',
        'coa_port', 'active', 'description',
    ];

    protected $hidden = ['secret_encrypted'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function setSecretAttribute(string $value): void
    {
        $this->attributes['secret_encrypted'] = Crypt::encryptString($value);
    }

    public function getSecretAttribute(): string
    {
        return Crypt::decryptString($this->secret_encrypted);
    }

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
