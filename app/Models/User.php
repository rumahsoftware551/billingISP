<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Foundation\Auth\User as Authenticatable; use Illuminate\Notifications\Notifiable;
class User extends Authenticatable { use HasFactory,Notifiable; protected $fillable=['name','email','password','is_platform_admin','status']; protected $hidden=['password','remember_token']; protected function casts():array{return ['email_verified_at'=>'datetime','password'=>'hashed','is_platform_admin'=>'boolean','last_login_at'=>'datetime'];} public function tenants(){return $this->belongsToMany(Tenant::class,'tenant_memberships')->withPivot(['role_id','is_default','status'])->withTimestamps();} }
