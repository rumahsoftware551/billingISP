<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PaymentGatewayTransaction extends Model {
    use BelongsToTenant;
    protected $guarded=[];
    protected function casts():array{return ['create_response'=>'array','status_response'=>'array','expires_at'=>'datetime','paid_at'=>'datetime','verified_at'=>'datetime'];}
    public function invoice(){return $this->belongsTo(Invoice::class);} public function payment(){return $this->belongsTo(Payment::class);}
}
