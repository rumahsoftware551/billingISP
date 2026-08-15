<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class CustomerService extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'internet_plan_id', 'router_id', 'network_nas_id',
        'ip_pool_id', 'service_number', 'service_type', 'pppoe_username', 'pppoe_password',
        'status', 'billing_day', 'due_day', 'static_ip', 'installed_at',
        'last_radius_sync_at', 'last_coa_at', 'last_disconnect_at', 'notes',
    ];

    protected $hidden = ['pppoe_password_encrypted'];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'last_radius_sync_at' => 'datetime',
            'last_coa_at' => 'datetime',
            'last_disconnect_at' => 'datetime',
        ];
    }

    public function setPppoePasswordAttribute(string $value): void
    {
        $this->attributes['pppoe_password_encrypted'] = Crypt::encryptString($value);
    }

    public function getPppoePasswordAttribute(): string
    {
        return Crypt::decryptString($this->pppoe_password_encrypted);
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function plan() { return $this->belongsTo(InternetPlan::class, 'internet_plan_id'); }
    public function router() { return $this->belongsTo(Router::class); }
    public function nas() { return $this->belongsTo(NetworkNas::class, 'network_nas_id'); }
    public function ipPool() { return $this->belongsTo(IpPool::class); }
    public function statusHistories() { return $this->hasMany(ServiceStatusHistory::class); }
    public function accountingSessions() { return $this->hasMany(Radacct::class, 'username', 'pppoe_username'); }
    public function radiusActions() { return $this->hasMany(RadiusActionLog::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function suspensions() { return $this->hasMany(ServiceSuspension::class); }
    public function activeBillingSuspension() { return $this->hasOne(ServiceSuspension::class)->where('source', 'billing_automation')->where('status', 'active')->latestOfMany(); }
    public function automationEvents() { return $this->hasMany(AutomationEvent::class); }
    public function supportTickets() { return $this->hasMany(SupportTicket::class); }
    public function workOrders() { return $this->hasMany(WorkOrder::class); }
    public function installationJobs() { return $this->hasMany(InstallationJob::class); }
    public function inventoryItems() { return $this->hasMany(InventoryItem::class, 'assigned_customer_service_id'); }
    public function networkAssignment() { return $this->hasOne(ServiceNetworkAssignment::class); }
}
