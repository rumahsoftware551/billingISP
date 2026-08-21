<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NetworkActionOutbox extends Model
{
    use BelongsToTenant;

    protected $table = 'network_action_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'completed_at' => 'datetime',
            'result' => 'array',
        ];
    }

    public function service() { return $this->belongsTo(CustomerService::class, 'customer_service_id'); }
    public function suspension() { return $this->belongsTo(ServiceSuspension::class, 'service_suspension_id'); }
    public function run() { return $this->belongsTo(AutomationRun::class, 'automation_run_id'); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
