<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseAcceptanceRun extends Model
{
    protected $fillable = ['run_uuid','version','environment','status','checks_total','checks_passed','checks_failed','checks_warning','source_manifest_sha256','summary','executed_by','started_at','completed_at','notes'];
    protected function casts(): array { return ['summary'=>'array','started_at'=>'datetime','completed_at'=>'datetime']; }
    public function findings(){ return $this->hasMany(SecurityAuditFinding::class); }
    public function user(){ return $this->belongsTo(User::class,'executed_by'); }
}
