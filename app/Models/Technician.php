<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Technician extends Model { use BelongsToTenant; protected $fillable=['tenant_id','user_id','code','name','phone','email','status','skills','notes']; protected function casts():array{return ['skills'=>'array'];} public function user(){return $this->belongsTo(User::class);} public function workOrders(){return $this->hasMany(WorkOrder::class);} public function installationJobs(){return $this->hasMany(InstallationJob::class);} public function inventoryItems(){return $this->hasMany(InventoryItem::class,'assigned_technician_id');} }
