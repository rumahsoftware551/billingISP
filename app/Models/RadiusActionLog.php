<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RadiusActionLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_service_id', 'radacctid', 'network_nas_id',
        'action', 'target', 'request_payload', 'response_code', 'success',
        'output', 'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'success' => 'boolean',
        ];
    }

    public function service() { return $this->belongsTo(CustomerService::class, 'customer_service_id'); }
    public function nas() { return $this->belongsTo(NetworkNas::class, 'network_nas_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
