<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CustomerPortalAccount extends Model
{
    protected $guarded = [];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'portal_enabled_at' => 'datetime',
        ];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function passwordMatches(string $plain): bool
    {
        return Hash::check($plain, $this->password);
    }
}
