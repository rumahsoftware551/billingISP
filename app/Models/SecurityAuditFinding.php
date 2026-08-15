<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuditFinding extends Model
{
    protected $fillable = ['release_acceptance_run_id','tenant_id','check_key','category','severity','status','title','detail','remediation','evidence'];
    protected function casts(): array { return ['evidence'=>'array']; }
    public function run(){ return $this->belongsTo(ReleaseAcceptanceRun::class,'release_acceptance_run_id'); }
    public function tenant(){ return $this->belongsTo(Tenant::class); }
}
