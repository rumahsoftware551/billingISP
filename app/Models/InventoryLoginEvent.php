<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class InventoryLoginEvent extends Model { use BelongsToTenant; public $timestamps=false; protected $fillable=['tenant_id','inventory_portal_account_id','event','ip_address','user_agent','meta','created_at']; protected function casts():array{return ['meta'=>'array','created_at'=>'datetime'];} }
