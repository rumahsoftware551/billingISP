<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function partnerAccount() { return $this->belongsTo(PartnerAccount::class, 'partner_account_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
    public function invoices() { return $this->belongsToMany(Invoice::class, 'payment_allocations')->withPivot('amount')->withTimestamps(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
