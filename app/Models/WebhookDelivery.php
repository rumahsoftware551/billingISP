<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'webhook_endpoint_id', 'event_id', 'event', 'payload', 'status', 'attempts', 'response_code', 'response_body', 'last_error', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime'];
    }

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
