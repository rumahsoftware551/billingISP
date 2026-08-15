<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ServiceSuspension extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function service() { return $this->belongsTo(CustomerService::class, 'customer_service_id'); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function resolvedByPayment() { return $this->belongsTo(Payment::class, 'resolved_by_payment_id'); }
}
