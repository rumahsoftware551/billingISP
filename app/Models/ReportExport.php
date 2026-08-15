<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    public function exporter()
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
