<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\NetworkNas;
use Illuminate\Support\Facades\DB;

class RadiusProjectionService
{
    public function syncNas(NetworkNas $nas): void
    {
        DB::table('nas')->updateOrInsert(
            ['nasname' => $nas->nasname],
            [
                'shortname' => $nas->shortname,
                'type' => $nas->type,
                'secret' => $nas->secret,
                'description' => $nas->description,
            ]
        );
    }

    public function removeNas(NetworkNas $nas): void
    {
        DB::table('nas')->where('nasname', $nas->nasname)->delete();
    }

    public function syncService(CustomerService $service): void
    {
        $service->loadMissing('plan', 'ipPool');

        if ($service->status === 'suspended') {
            $this->syncRejectService($service);
            $service->forceFill(['last_radius_sync_at' => now()])->saveQuietly();
            return;
        }

        if ($service->status !== 'active') {
            $this->removeServiceByUsername($service->pppoe_username);
            $service->forceFill(['last_radius_sync_at' => now()])->saveQuietly();
            return;
        }

        DB::transaction(function () use ($service) {
            // Active service projection must never inherit a stale Reject marker
            // or any other check item from a previous lifecycle state.
            DB::table('radcheck')->where('username', $service->pppoe_username)->delete();
            DB::table('radusergroup')->where('username', $service->pppoe_username)->delete();

            DB::table('radcheck')->insert([
                'username' => $service->pppoe_username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $service->pppoe_password,
            ]);

            // Reply rows for service usernames are a projection of Jaringanku's service domain.
            DB::table('radreply')->where('username', $service->pppoe_username)->delete();

            $attributes = (array) ($service->plan?->radius_attributes ?? []);
            if (! array_key_exists('Acct-Interim-Interval', $attributes)) {
                $attributes['Acct-Interim-Interval'] = max(60, (int) ($service->plan?->acct_interim_interval ?? 300));
            }
            if (! array_key_exists('Class', $attributes)) {
                $attributes['Class'] = 'jaringanku-service-'.$service->id;
            }

            foreach ($attributes as $attribute => $value) {
                DB::table('radreply')->insert([
                    'username' => $service->pppoe_username,
                    'attribute' => (string) $attribute,
                    'op' => ':=',
                    'value' => (string) $value,
                ]);
            }

            if ($service->static_ip) {
                DB::table('radreply')->insert([
                    'username' => $service->pppoe_username,
                    'attribute' => 'Framed-IP-Address',
                    'op' => ':=',
                    'value' => $service->static_ip,
                ]);
            } elseif ($service->ipPool) {
                DB::table('radreply')->insert([
                    'username' => $service->pppoe_username,
                    'attribute' => 'Framed-Pool',
                    'op' => ':=',
                    'value' => $service->ipPool->name,
                ]);
            }

            $service->forceFill(['last_radius_sync_at' => now()])->saveQuietly();
        });
    }


    private function syncRejectService(CustomerService $service): void
    {
        DB::transaction(function () use ($service) {
            // Suspended accounts keep an explicit reject projection instead of
            // disappearing from SQL completely.  This makes the RADIUS result
            // deterministic: the NAS receives Access-Reject rather than a
            // timeout / ambiguous unknown-user path.
            DB::table('radcheck')->where('username', $service->pppoe_username)->delete();
            DB::table('radreply')->where('username', $service->pppoe_username)->delete();
            DB::table('radusergroup')->where('username', $service->pppoe_username)->delete();

            DB::table('radcheck')->insert([
                'username' => $service->pppoe_username,
                'attribute' => 'Auth-Type',
                'op' => ':=',
                'value' => 'Reject',
            ]);
        });
    }

    public function removeService(CustomerService $service): void
    {
        $this->removeServiceByUsername($service->pppoe_username);
    }

    public function removeServiceByUsername(string $username): void
    {
        DB::transaction(function () use ($username) {
            DB::table('radcheck')->where('username', $username)->delete();
            DB::table('radreply')->where('username', $username)->delete();
            DB::table('radusergroup')->where('username', $username)->delete();
        });
    }
}
