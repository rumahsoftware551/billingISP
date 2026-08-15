<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ServiceStatusHistory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_service_id', 'from_status', 'to_status',
        'reason', 'actor_user_id', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function service() { return $this->belongsTo(CustomerService::class, 'customer_service_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
