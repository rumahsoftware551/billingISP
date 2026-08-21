<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBranding extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['show_powered_by' => 'boolean']; }
    public function tenant(){ return $this->belongsTo(Tenant::class); }
}
