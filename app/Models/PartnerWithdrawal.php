<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PartnerWithdrawal extends Model { use BelongsToTenant; protected $guarded=[]; protected function casts():array{return ['payout_account'=>'array','requested_at'=>'datetime','processed_at'=>'datetime'];} public function partner(){return $this->belongsTo(Partner::class);} public function account(){return $this->belongsTo(PartnerAccount::class,'partner_account_id');} }
