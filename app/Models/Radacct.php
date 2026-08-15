<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Radacct extends Model
{
    protected $table = 'radacct';
    protected $primaryKey = 'radacctid';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acctstarttime' => 'datetime',
            'acctupdatetime' => 'datetime',
            'acctstoptime' => 'datetime',
            'acctinterval' => 'integer',
            'acctsessiontime' => 'integer',
            'acctinputoctets' => 'integer',
            'acctoutputoctets' => 'integer',
        ];
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->whereNull('acctstoptime');
    }

    public function service()
    {
        return $this->belongsTo(CustomerService::class, 'username', 'pppoe_username');
    }

    public function nas()
    {
        return $this->belongsTo(NetworkNas::class, 'nasipaddress', 'nasname');
    }
}
