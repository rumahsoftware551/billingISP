<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformEvent extends Model
{
    protected $fillable = ['tenant_id','user_id','event','severity','payload'];
    protected function casts(): array { return ['payload'=>'array']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user() { return $this->belongsTo(User::class); }
}
