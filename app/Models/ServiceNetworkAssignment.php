<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ServiceNetworkAssignment extends Model { use BelongsToTenant; protected $fillable=['tenant_id','customer_service_id','network_node_id','port_number','cable_length_m','notes']; public function service(){return $this->belongsTo(CustomerService::class,'customer_service_id');} public function node(){return $this->belongsTo(NetworkNode::class,'network_node_id');} }
