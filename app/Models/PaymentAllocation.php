<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function payment() { return $this->belongsTo(Payment::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
