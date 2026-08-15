<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class NetworkNode extends Model { use BelongsToTenant; protected $fillable=['tenant_id','parent_node_id','code','name','node_type','status','address','latitude','longitude','capacity_ports','used_ports','notes','metadata']; protected function casts():array{return ['latitude'=>'decimal:7','longitude'=>'decimal:7','metadata'=>'array'];} public function parent(){return $this->belongsTo(self::class,'parent_node_id');} public function children(){return $this->hasMany(self::class,'parent_node_id');} public function assignments(){return $this->hasMany(ServiceNetworkAssignment::class);} }
