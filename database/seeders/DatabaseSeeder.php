<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerPortalAccount;
use App\Models\BillingProfile;
use App\Models\InternetPlan;
use App\Models\ServiceStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RadiusProjectionService;
use App\Support\CurrentTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => env('SEED_TENANT_SLUG', 'demo-isp')],
            [
                'name' => env('SEED_TENANT_NAME', 'Jaringanku Demo ISP'),
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
            ]
        );

        $plans = [
            ['code'=>'STARTER','name'=>'Starter','monthly_price'=>199000,'max_customers'=>500,'max_services'=>600,'max_routers'=>3,'max_users'=>5,'features'=>['billing','radius','portal']],
            ['code'=>'GROWTH','name'=>'Growth','monthly_price'=>499000,'max_customers'=>3000,'max_services'=>3500,'max_routers'=>15,'max_users'=>20,'features'=>['billing','radius','portal','whatsapp','field_ops','partner_portal','inventory_portal']],
            ['code'=>'PRO','name'=>'Pro','monthly_price'=>999000,'max_customers'=>10000,'max_services'=>12000,'max_routers'=>50,'max_users'=>100,'features'=>['billing','radius','portal','whatsapp','field_ops','reports','webhooks','partner_portal','inventory_portal']],
            ['code'=>'ENTERPRISE','name'=>'Enterprise','monthly_price'=>0,'max_customers'=>null,'max_services'=>null,'max_routers'=>null,'max_users'=>null,'features'=>['all']],
        ];
        foreach ($plans as $planData) {
            \App\Models\PlatformPlan::firstOrCreate(['code'=>$planData['code']], [...$planData, 'active'=>true]);
        }
        $defaultPlan = \App\Models\PlatformPlan::where('code', env('SEED_PLATFORM_PLAN', 'PRO'))->firstOrFail();
        \App\Models\TenantSubscription::firstOrCreate(
            ['tenant_id'=>$tenant->id],
            ['platform_plan_id'=>$defaultPlan->id,'status'=>'active','current_period_start'=>now(),'current_period_end'=>now()->addYear(),'grace_ends_at'=>null]
        );

        $roleId = DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'owner')
            ->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'tenant_id' => $tenant->id,
                'name' => 'Owner',
                'slug' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@jaringanku.local')],
            [
                'name' => env('SEED_ADMIN_NAME', 'Administrator Jaringanku'),
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'Jaringanku123!')),
                'is_platform_admin' => true,
            ]
        );
        if (! $user->is_platform_admin) {
            $user->forceFill(['is_platform_admin' => true])->save();
        }

        DB::table('tenant_memberships')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            ['role_id' => $roleId, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        // Phase 16 commercial-readiness: permission catalogue, role templates,
        // tenant branding, and custom payment methods are seeded before demo-only data.
        $permissionMap = [
            'dashboard.view' => 'Lihat dashboard',
            'customers.view' => 'Lihat pelanggan',
            'customers.manage' => 'Kelola pelanggan',
            'billing.view' => 'Lihat billing',
            'billing.manage' => 'Kelola billing & pembayaran',
            'network.view' => 'Lihat jaringan',
            'network.manage' => 'Kelola jaringan',
            'operations.view' => 'Lihat automation/isolir',
            'operations.manage' => 'Kelola automation/isolir',
            'field_ops.view' => 'Lihat teknisi, tiket & work order',
            'field_ops.manage' => 'Kelola teknisi, tiket & work order',
            'partners.view' => 'Lihat mitra',
            'partners.manage' => 'Kelola mitra',
            'inventory.view' => 'Lihat inventory',
            'inventory.manage' => 'Kelola inventory',
            'reports.view' => 'Lihat laporan',
            'integrations.manage' => 'Kelola integrasi',
            'settings.manage' => 'Kelola branding, payment & user',
            'system.manage' => 'Kelola system settings',
        ];
        foreach ($permissionMap as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug'=>$slug],
                ['name'=>$name,'created_at'=>now(),'updated_at'=>now()]
            );
        }
        $permissionIds = DB::table('permissions')->pluck('id','slug');
        $roleTemplates = [
            'admin' => ['Administrator', array_keys($permissionMap)],
            'finance' => ['Finance / Billing', ['dashboard.view','customers.view','billing.view','billing.manage','reports.view']],
            'cs' => ['Customer Service', ['dashboard.view','customers.view','customers.manage','billing.view','field_ops.view','field_ops.manage']],
            'noc' => ['NOC / Network', ['dashboard.view','customers.view','network.view','network.manage','operations.view','operations.manage','field_ops.view']],
            'warehouse' => ['Warehouse', ['dashboard.view','inventory.view','inventory.manage','field_ops.view']],
            'viewer' => ['Read Only', ['dashboard.view','customers.view','billing.view','network.view','operations.view','field_ops.view','partners.view','inventory.view','reports.view']],
        ];
        foreach ($roleTemplates as $slug => [$name, $slugs]) {
            $rid = DB::table('roles')->where('tenant_id',$tenant->id)->where('slug',$slug)->value('id');
            if (!$rid) {
                $rid = DB::table('roles')->insertGetId(['tenant_id'=>$tenant->id,'name'=>$name,'slug'=>$slug,'description'=>'Template role Phase 16','created_at'=>now(),'updated_at'=>now()]);
            }
            DB::table('permission_role')->where('role_id',$rid)->delete();
            foreach ($slugs as $permissionSlug) {
                if ($permissionIds->has($permissionSlug)) {
                    DB::table('permission_role')->insertOrIgnore(['permission_id'=>$permissionIds[$permissionSlug],'role_id'=>$rid]);
                }
            }
        }

        \App\Models\TenantBranding::query()->firstOrCreate(
            ['tenant_id'=>$tenant->id],
            ['app_name'=>'Jaringanku','company_name'=>$tenant->name,'portal_title'=>'Internet Service Management','primary_color'=>'#0f6cbd','accent_color'=>'#16a34a','show_powered_by'=>true]
        );
        \App\Models\CustomPaymentMethod::query()->firstOrCreate(
            ['code'=>'cash-loket'],
            ['name'=>'Tunai / Loket','type'=>'cash','instructions'=>'Bayar langsung ke loket/collector resmi ISP.','admin_fee_type'=>'none','admin_fee_value'=>0,'minimum_amount'=>0,'customer_visible'=>true,'partner_visible'=>true,'active'=>true,'sort_order'=>10]
        );
        \App\Models\CustomPaymentMethod::query()->firstOrCreate(
            ['code'=>'qris-manual'],
            ['name'=>'QRIS Manual','type'=>'qris','instructions'=>'Admin dapat upload gambar QRIS pada Pengaturan > Metode Pembayaran.','admin_fee_type'=>'none','admin_fee_value'=>0,'minimum_amount'=>0,'customer_visible'=>true,'partner_visible'=>true,'active'=>false,'sort_order'=>20]
        );

        $plan = InternetPlan::updateOrCreate(
            ['code' => 'HOME20'],
            [
                'name' => 'Home 20 Mbps',
                'price' => 250000,
                'download_kbps' => 20000,
                'upload_kbps' => 10000,
                'acct_interim_interval' => 300,
                'active' => true,
                'radius_attributes' => ['Mikrotik-Rate-Limit' => '10000k/20000k'],
            ]
        );

        BillingProfile::updateOrCreate(
            ['name' => 'Default Billing'],
            [
                'invoice_day' => 1,
                'due_day' => 10,
                'grace_days' => 3,
                'auto_suspend' => true,
                'auto_reactivate' => true,
                'disconnect_on_suspend' => true,
                'active' => true,
            ]
        );

        \App\Models\NotificationTemplate::updateOrCreate(
            ['code' => 'system.test'],
            [
                'name' => 'System Test',
                'channel' => 'log',
                'subject' => 'Jaringanku system test',
                'body' => 'Halo {{name}}, notification engine Jaringanku aktif.',
                'variables' => ['name'],
                'enabled' => true,
            ]
        );


        foreach ([
            'billing.invoice_created' => [
                'name' => 'Invoice Created',
                'subject' => 'Tagihan {{invoice}}',
                'body' => 'Halo {{name}}, tagihan {{invoice}} sebesar {{amount}} telah terbit. Jatuh tempo {{due_date}}.',
                'variables' => ['name','invoice','amount','due_date'],
            ],
            'billing.overdue' => [
                'name' => 'Invoice Overdue',
                'subject' => 'Tagihan jatuh tempo {{invoice}}',
                'body' => 'Halo {{name}}, tagihan {{invoice}} sebesar {{amount}} telah melewati jatuh tempo {{due_date}}. Mohon lakukan pembayaran.',
                'variables' => ['name','invoice','amount','due_date'],
            ],
            'billing.payment_received' => [
                'name' => 'Payment Received',
                'subject' => 'Pembayaran {{payment}} diterima',
                'body' => 'Halo {{name}}, pembayaran {{payment}} untuk {{invoice}} sebesar {{amount}} sudah diterima. Sisa tagihan {{balance}}.',
                'variables' => ['name','payment','invoice','amount','balance'],
            ],
        ] as $code => $template) {
            \App\Models\NotificationTemplate::updateOrCreate(
                ['code' => $code],
                [...$template, 'channel' => 'whatsapp', 'enabled' => true]
            );
        }

        \App\Models\PaymentGatewaySetting::query()->firstOrCreate([], [
            'provider' => app()->environment('local') ? 'mock' : 'midtrans',
            'environment' => 'sandbox',
            'enabled' => app()->environment('local'),
            'enabled_payments' => ['gopay','shopeepay','other_qris','bca_va','bni_va','bri_va','permata_va'],
            'expiry_minutes' => 60,
        ]);
        \App\Models\WhatsAppSetting::query()->firstOrCreate([], [
            'provider' => 'meta_cloud',
            'mode' => app()->environment('local') ? 'log' : 'cloud',
            'enabled' => app()->environment('local'),
            'graph_version' => 'v26.0',
            'default_country_code' => '62',
            'template_language' => 'id',
            'template_map' => [],
        ]);

        if (! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        // Keep the Phase 02 smoke-test user in local/demo only.
        DB::table('radcheck')->updateOrInsert(
            ['username' => 'phase2-test', 'attribute' => 'Cleartext-Password'],
            ['op' => ':=', 'value' => env('RADIUS_TEST_PASSWORD', 'Phase2Test123!')]
        );
        DB::table('radreply')->updateOrInsert(
            ['username' => 'phase2-test', 'attribute' => 'Mikrotik-Rate-Limit'],
            ['op' => ':=', 'value' => '10M/20M']
        );

        DB::table('tenant_sequences')->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'key' => 'customer',
            'value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tenant_sequences')->where('tenant_id', $tenant->id)->where('key', 'customer')->where('value', '<', 1)->update(['value' => 1]);
        DB::table('tenant_sequences')->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'key' => 'service',
            'value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tenant_sequences')->where('tenant_id', $tenant->id)->where('key', 'service')->where('value', '<', 1)->update(['value' => 1]);

        $customer = Customer::updateOrCreate(
            ['customer_number' => 'JRG-000001'],
            [
                'name' => 'Pelanggan Demo Phase 03',
                'customer_type' => 'residential',
                'email' => 'demo@jaringanku.local',
                'phone' => '081234567890',
                'status' => 'active',
                'notes' => 'Data demo otomatis untuk verifikasi Phase 03.',
            ]
        );


        $partner = \App\Models\Partner::query()->updateOrCreate(
            ['code' => 'MTR-00001'],
            [
                'name' => 'Mitra Demo Jaringanku',
                'status' => 'active',
                'email' => 'mitra@jaringanku.local',
                'phone' => '081200000013',
                'area_name' => 'Area Demo',
                'payout_account' => ['bank'=>'BCA','account'=>'1234567890','holder'=>'Mitra Demo Jaringanku'],
                'notes' => 'Mitra demo untuk acceptance Phase 13.',
            ]
        );
        \App\Models\PartnerAccount::query()->updateOrCreate(
            ['email' => 'mitra@jaringanku.local'],
            [
                'partner_id' => $partner->id,
                'name' => 'Owner Mitra Demo',
                'password' => env('PHASE13_PARTNER_PASSWORD', 'MitraDemo123!'),
                'role' => 'owner',
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        \App\Models\PartnerCommissionRule::query()->updateOrCreate(
            ['partner_id'=>$partner->id,'name'=>'Komisi Pembayaran 10%'],
            ['type'=>'payment_percent','value'=>1000,'active'=>true]
        );
        $customer->forceFill(['partner_id'=>$partner->id])->save();

        // Phase 14 Inventory / Warehouse Portal demo baseline.
        $warehouse = \App\Models\InventoryLocation::query()->updateOrCreate(
            ['code'=>'WH-MAIN'],
            ['name'=>'Gudang Utama','location_type'=>'warehouse','address'=>'Gudang demo Jaringanku','active'=>true]
        );
        $demoTechnician = \App\Models\Technician::query()->updateOrCreate(
            ['code'=>'TECH-DEMO'],
            ['name'=>'Teknisi Demo Inventory','phone'=>'081400000014','status'=>'active','skills'=>['fiber','inventory']]
        );
        \App\Models\InventoryLocation::query()->updateOrCreate(
            ['code'=>'TECH-DEMO-STOCK'],
            ['name'=>'Stok Teknisi Demo','location_type'=>'technician','technician_id'=>$demoTechnician->id,'active'=>true]
        );
        \App\Models\InventoryPortalAccount::query()->updateOrCreate(
            ['email'=>'inventory@jaringanku.local'],
            ['inventory_location_id'=>$warehouse->id,'name'=>'Warehouse Manager Demo','password'=>env('PHASE14_INVENTORY_PASSWORD','InventoryDemo123!'),'role'=>'warehouse_manager','status'=>'active','must_change_password'=>false]
        );
        \App\Models\InventorySku::query()->updateOrCreate(
            ['sku'=>'ONT-DEMO'],
            ['name'=>'ONT Demo GPON','category'=>'ont','brand'=>'Demo','model'=>'ONT-X1','uom'=>'pcs','minimum_stock'=>2,'serialized'=>true,'track_mac'=>true,'active'=>true]
        );
        \App\Models\InventorySku::query()->updateOrCreate(
            ['sku'=>'DROPCORE-1C'],
            ['name'=>'Kabel Dropcore 1 Core','category'=>'cable','uom'=>'meter','minimum_stock'=>100,'serialized'=>false,'track_mac'=>false,'active'=>true]
        );
        \App\Models\InventorySupplier::query()->updateOrCreate(
            ['code'=>'SUP-DEMO'],
            ['name'=>'Supplier Demo Fiber','contact_name'=>'Sales Demo','phone'=>'081400000099','active'=>true]
        );

        CustomerPortalAccount::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'tenant_id' => $tenant->id,
                'email' => 'demo@jaringanku.local',
                'password' => Hash::make(env('PHASE10_PORTAL_PASSWORD', 'PortalDemo123!')),
                'status' => 'active',
                'must_change_password' => false,
                'portal_enabled_at' => now(),
            ]
        );

        $customer->addresses()->updateOrCreate(
            ['label' => 'Instalasi'],
            [
                'address_line' => 'Alamat demo instalasi Jaringanku',
                'city' => 'Pekanbaru',
                'province' => 'Riau',
                'is_primary' => true,
            ]
        );

        $service = CustomerService::updateOrCreate(
            ['pppoe_username' => 'phase3-demo'],
            [
                'customer_id' => $customer->id,
                'internet_plan_id' => $plan->id,
                'service_number' => 'SRV-000001',
                'service_type' => 'pppoe',
                'pppoe_password' => env('PHASE3_DEMO_PPPOE_PASSWORD', 'Phase3Demo123!'),
                'status' => 'active',
                'billing_day' => 1,
                'due_day' => 10,
                'installed_at' => now(),
                'notes' => 'PPPoE demo Phase 03.',
            ]
        );

        ServiceStatusHistory::firstOrCreate(
            [
                'customer_service_id' => $service->id,
                'from_status' => null,
                'to_status' => 'active',
            ],
            [
                'reason' => 'Seed demo Phase 03',
                'actor_user_id' => $user->id,
            ]
        );

        app(RadiusProjectionService::class)->syncService($service);
    }
}
