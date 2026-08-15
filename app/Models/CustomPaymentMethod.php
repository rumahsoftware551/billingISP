<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomPaymentMethod extends Model
{
    use BelongsToTenant;
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'customer_visible'=>'boolean','partner_visible'=>'boolean','active'=>'boolean',
            'admin_fee_value'=>'integer','minimum_amount'=>'integer','maximum_amount'=>'integer',
            'sort_order'=>'integer','meta'=>'array',
        ];
    }
    public function proofs(){ return $this->hasMany(ManualPaymentProof::class); }
}
