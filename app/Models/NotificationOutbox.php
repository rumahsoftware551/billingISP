<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationOutbox extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_outbox';
    protected $fillable = ['tenant_id', 'notification_template_id', 'channel', 'recipient', 'subject', 'body', 'payload', 'status', 'attempts', 'available_at', 'sent_at', 'last_error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }
}
