<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PartnerCommissionRule extends Model { use BelongsToTenant; protected $guarded=[]; protected function casts():array{return ['active'=>'boolean','starts_at'=>'date','ends_at'=>'date','meta'=>'array'];} public function partner(){return $this->belongsTo(Partner::class);} }
