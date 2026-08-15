<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function service() { return $this->belongsTo(CustomerService::class, 'customer_service_id'); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
    public function payments() { return $this->belongsToMany(Payment::class, 'payment_allocations')->withPivot('amount')->withTimestamps(); }
    public function gatewayTransactions() { return $this->hasMany(PaymentGatewayTransaction::class); }
}
