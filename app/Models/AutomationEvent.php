<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AutomationEvent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function run() { return $this->belongsTo(AutomationRun::class, 'automation_run_id'); }
    public function service() { return $this->belongsTo(CustomerService::class, 'customer_service_id'); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
}
