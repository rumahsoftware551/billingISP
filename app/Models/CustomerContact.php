<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'customer_id', 'label', 'type', 'value', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
