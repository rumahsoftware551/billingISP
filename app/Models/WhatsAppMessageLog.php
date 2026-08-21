<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class WhatsAppMessageLog extends Model {
    use BelongsToTenant;
    protected $table='whatsapp_message_logs';
    protected $guarded=[];
    protected function casts():array{return ['response'=>'array'];}
    public function notification(){return $this->belongsTo(NotificationOutbox::class,'notification_outbox_id');}
}
