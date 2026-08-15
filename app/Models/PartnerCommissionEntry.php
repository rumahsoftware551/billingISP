<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PartnerCommissionEntry extends Model { use BelongsToTenant; protected $guarded=[]; protected function casts():array{return ['earned_at'=>'datetime','paid_at'=>'datetime','meta'=>'array'];} public function partner(){return $this->belongsTo(Partner::class);} public function customer(){return $this->belongsTo(Customer::class);} public function payment(){return $this->belongsTo(Payment::class);} public function rule(){return $this->belongsTo(PartnerCommissionRule::class,'partner_commission_rule_id');} }
