<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PaymentGatewaySetting extends Model {
    use BelongsToTenant;
    protected $guarded=[];
    protected function casts():array{return ['enabled'=>'boolean','enabled_payments'=>'array','client_key'=>'encrypted','server_key'=>'encrypted','last_tested_at'=>'datetime'];}
}
