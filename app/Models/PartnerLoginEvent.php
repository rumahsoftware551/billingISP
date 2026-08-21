<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PartnerLoginEvent extends Model { use BelongsToTenant; public $timestamps=false; protected $guarded=[]; protected function casts():array{return ['created_at'=>'datetime','meta'=>'array'];} }
