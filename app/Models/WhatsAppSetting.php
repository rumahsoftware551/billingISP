<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class WhatsAppSetting extends Model {
    use BelongsToTenant;
    protected $table='whatsapp_settings';
    protected $guarded=[];
    protected function casts():array{return ['enabled'=>'boolean','template_map'=>'array','access_token'=>'encrypted','app_secret'=>'encrypted','verify_token'=>'encrypted','last_tested_at'=>'datetime'];}
}
