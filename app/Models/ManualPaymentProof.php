<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ManualPaymentProof extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected function casts(): array { return ['amount'=>'integer','reviewed_at'=>'datetime']; }
    public function invoice(){ return $this->belongsTo(Invoice::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function method(){ return $this->belongsTo(CustomPaymentMethod::class, 'custom_payment_method_id'); }
    public function portalAccount(){ return $this->belongsTo(CustomerPortalAccount::class, 'customer_portal_account_id'); }
    public function reviewer(){ return $this->belongsTo(User::class, 'reviewed_by'); }
    public function payment(){ return $this->belongsTo(Payment::class); }
}
