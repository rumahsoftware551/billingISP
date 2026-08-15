<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InstallationJob extends Model { use BelongsToTenant; protected $fillable=['tenant_id','installation_number','customer_service_id','technician_id','work_order_id','status','scheduled_at','arrived_at','activated_at','completed_at','installation_notes','activation_data']; protected function casts():array{return ['scheduled_at'=>'datetime','arrived_at'=>'datetime','activated_at'=>'datetime','completed_at'=>'datetime','activation_data'=>'array'];} public function service(){return $this->belongsTo(CustomerService::class,'customer_service_id');} public function technician(){return $this->belongsTo(Technician::class);} public function workOrder(){return $this->belongsTo(WorkOrder::class);} }
