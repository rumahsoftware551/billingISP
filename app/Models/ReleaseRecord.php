<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseRecord extends Model
{
    protected $fillable = ['version','environment','schema_version','git_commit','deployed_by','status','notes','deployed_at'];
    protected function casts(): array { return ['deployed_at'=>'datetime']; }
    public function user() { return $this->belongsTo(User::class, 'deployed_by'); }
}
