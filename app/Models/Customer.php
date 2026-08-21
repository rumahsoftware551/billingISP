<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'partner_id', 'created_by_partner_account_id', 'customer_number', 'name', 'customer_type', 'identity_number',
        'email', 'phone', 'secondary_phone', 'status', 'notes',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function services()
    {
        return $this->hasMany(CustomerService::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function portalAccount()
    {
        return $this->hasOne(CustomerPortalAccount::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }
}
