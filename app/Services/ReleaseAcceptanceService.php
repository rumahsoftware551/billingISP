<?php
namespace App\Services;

use App\Models\ReleaseAcceptanceRun;
use App\Models\SecurityAuditFinding;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReleaseAcceptanceService
{
    private array $findings = [];

    public function run(bool $persist = true, ?int $userId = null, bool $strictProduction = false, ?string $notes = null): array
    {
        $this->findings = [];
        $started = now();
        $run = null;
        $manifest = base_path('RELEASE-SHA256SUMS.txt');
        $manifestHash = is_file($manifest) ? hash_file('sha256', $manifest) : null;

        if ($persist) {
            $run = ReleaseAcceptanceRun::create([
                'run_uuid' => (string) Str::uuid(),
                'version' => (string) config('jaringanku.version'),
                'environment' => app()->environment(),
                'status' => 'running',
                'source_manifest_sha256' => $manifestHash,
                'executed_by' => $userId,
                'started_at' => $started,
                'notes' => $notes,
            ]);
        }

        $this->checkReleaseMetadata();
        $this->checkRequiredSchema();
        $this->checkRouteProtection();
        $this->checkTenantScopedModels();
        $this->checkCredentialStorage();
        $this->checkDataIsolationAndIntegrity();
        $this->checkBillingReconciliation();
        $this->checkPartnerReconciliation();
        $this->checkInventoryReconciliation();
        $this->checkRuntimeHardening($strictProduction);
        $this->checkSourceManifest();

        $summary = $this->summary();
        if ($persist && $run) {
            DB::transaction(function () use ($run, $summary) {
                foreach ($this->findings as $finding) {
                    SecurityAuditFinding::create(['release_acceptance_run_id'=>$run->id, ...$finding]);
                }
                $run->update([
                    'status' => $summary['failed'] === 0 ? 'passed' : 'failed',
                    'checks_total' => $summary['total'],
                    'checks_passed' => $summary['passed'],
                    'checks_failed' => $summary['failed'],
                    'checks_warning' => $summary['warning'],
                    'summary' => $summary,
                    'completed_at' => now(),
                ]);
            });
            $run->refresh();
        }

        return ['run'=>$run, 'summary'=>$summary, 'findings'=>$this->findings];
    }

    private function add(string $key, string $category, string $status, string $severity, string $title, ?string $detail = null, ?string $remediation = null, array $evidence = []): void
    {
        $this->findings[] = compact('key','category','status','severity','title','detail','remediation','evidence');
        $last = array_pop($this->findings);
        $last['check_key'] = $last['key']; unset($last['key']);
        $this->findings[] = $last;
    }

    private function countStatus(string $status): int { return count(array_filter($this->findings, fn($x)=>$x['status']===$status)); }
    private function summary(): array
    {
        return [
            'version'=>(string)config('jaringanku.version'),
            'channel'=>(string)config('jaringanku.release_channel'),
            'environment'=>app()->environment(),
            'total'=>count($this->findings),
            'passed'=>$this->countStatus('pass'),
            'failed'=>$this->countStatus('fail'),
            'warning'=>$this->countStatus('warn'),
        ];
    }

    private function checkReleaseMetadata(): void
    {
        $version=trim((string)config('jaringanku.version')); $channel=trim((string)config('jaringanku.release_channel'));
        $versionOk=$version!=='' && version_compare($version,'1.1.0','>=');
        $channelOk=in_array($channel,['stable','development'],true) && !($version==='1.1.0' && $channel!=='stable');
        $this->add('release.version','release',$versionOk?'pass':'fail','critical','Product version satisfies the Phase 15 baseline (>= v1.1.0)',"version={$version}",'Use JARINGANKU_VERSION=1.1.0 or a newer cumulative release version.');
        $this->add('release.channel','release',$channelOk?'pass':'fail','critical','Release channel is compatible with the cumulative release',"channel={$channel}; version={$version}",'v1.1.0 must remain stable; newer development phases may use RELEASE_CHANNEL=development.');
    }

    private function checkRequiredSchema(): void
    {
        $tables=['tenants','users','customers','customer_services','routers','network_nas','radcheck','radacct','invoices','payments','payment_allocations','automation_runs','report_exports','security_events','payment_gateway_settings','whatsapp_settings','customer_portal_accounts','support_tickets','inventory_items','platform_plans','tenant_subscriptions','partners','partner_accounts','partner_commission_entries','inventory_locations','inventory_portal_accounts','inventory_balances','inventory_transactions','release_acceptance_runs','security_audit_findings'];
        $missing=array_values(array_filter($tables,fn($t)=>!Schema::hasTable($t)));
        $this->add('schema.required','database',empty($missing)?'pass':'fail','critical','All Phase 01-15 tables are present',empty($missing)?'All required tables found.':'Missing: '.implode(', ',$missing),'Run php artisan migrate --force.',['missing'=>$missing]);
    }

    private function middlewareFor(string $name): array
    {
        $route=Route::getRoutes()->getByName($name); return $route ? $route->gatherMiddleware() : [];
    }
    private function routeGuard(string $name, array $required): void
    {
        $m=$this->middlewareFor($name); $missing=array_values(array_filter($required,fn($x)=>!in_array($x,$m,true)));
        $this->add('route.'.$name,'rbac',empty($missing)?'pass':'fail','critical',"Route {$name} has required middleware",'middleware='.implode(',',$m),empty($missing)?null:'Restore required middleware: '.implode(', ',$missing),['missing'=>$missing]);
    }
    private function checkRouteProtection(): void
    {
        $this->routeGuard('customers.index',['auth','tenant','subscription']);
        $this->routeGuard('system.index',['auth','tenant','subscription','system-admin']);
        $this->routeGuard('platform.index',['auth','platform-admin']);
        $this->routeGuard('portal.dashboard',['portal.auth']);
        $this->routeGuard('partner.dashboard',['partner.auth']);
        $this->routeGuard('inventory.dashboard',['inventory.auth']);
        $this->routeGuard('platform.release',['auth','platform-admin']);
    }

    private function checkTenantScopedModels(): void
    {
        $models=[\App\Models\Customer::class,\App\Models\CustomerService::class,\App\Models\Invoice::class,\App\Models\Payment::class,\App\Models\Router::class,\App\Models\NetworkNas::class,\App\Models\Partner::class,\App\Models\PartnerAccount::class,\App\Models\InventoryLocation::class,\App\Models\InventorySku::class,\App\Models\InventoryItem::class,\App\Models\InventoryTransaction::class,\App\Models\SupportTicket::class,\App\Models\WorkOrder::class];
        $missing=[]; foreach($models as $class){if(!in_array(BelongsToTenant::class,class_uses_recursive($class),true))$missing[]=$class;}
        $this->add('tenant.model_scopes','isolation',empty($missing)?'pass':'fail','critical','Core tenant models use BelongsToTenant global scope',empty($missing)?'All core models scoped.':'Unscoped: '.implode(', ',$missing),'Add BelongsToTenant to every tenant-owned model.',['models'=>$missing]);
    }

    private function checkCredentialStorage(): void
    {
        foreach ([['customer_portal_accounts','password'],['partner_accounts','password'],['inventory_portal_accounts','password']] as [$table,$column]) {
            if(!Schema::hasTable($table)) continue;
            $bad=DB::table($table)->whereNotNull($column)->get([$column])->filter(fn($r)=>!preg_match('/^\$(2[aby]|argon2i|argon2id)\$/',(string)$r->{$column}))->count();
            $this->add("credential.hash.{$table}",'secrets',$bad===0?'pass':'fail','critical',"{$table} passwords are hashed","invalid_hash_count={$bad}",'Reset any account whose password is not a recognized password hash.');
        }

        $encrypted=[
            ['routers','api_password_encrypted'],['network_nas','secret_encrypted'],
            ['payment_gateway_settings','client_key'],['payment_gateway_settings','server_key'],
            ['whatsapp_settings','access_token'],['whatsapp_settings','app_secret'],['whatsapp_settings','verify_token'],
            ['webhook_endpoints','secret'],
        ];
        foreach($encrypted as [$table,$column]){
            if(!Schema::hasTable($table)||!Schema::hasColumn($table,$column))continue;
            $bad=0;$checked=0;
            foreach(DB::table($table)->whereNotNull($column)->pluck($column) as $value){if($value==='')continue;$checked++;try{Crypt::decryptString((string)$value);}catch(\Throwable){$bad++;}}
            $this->add("credential.encrypted.{$table}.{$column}",'secrets',$bad===0?'pass':'fail','high',"{$table}.{$column} is Laravel-encrypted","checked={$checked}; decrypt_failures={$bad}",'Re-save affected credentials through the application so they are encrypted at rest.');
        }
    }

    private function mismatch(string $key, string $title, string $leftTable, string $leftFk, string $rightTable): void
    {
        $count=DB::table($leftTable.' as l')->join($rightTable.' as r','r.id','=','l.'.$leftFk)->whereColumn('l.tenant_id','<>','r.tenant_id')->count();
        $this->add($key,'isolation',$count===0?'pass':'fail','critical',$title,"mismatch_count={$count}",'Repair cross-tenant foreign ownership before release.');
    }
    private function checkDataIsolationAndIntegrity(): void
    {
        $this->mismatch('data.service_customer_tenant','Customer services match customer tenant','customer_services','customer_id','customers');
        $this->mismatch('data.invoice_customer_tenant','Invoices match customer tenant','invoices','customer_id','customers');
        $this->mismatch('data.payment_customer_tenant','Payments match customer tenant','payments','customer_id','customers');
        $this->mismatch('data.partner_customer_tenant','Partner-assigned customers match partner tenant','customers','partner_id','partners');
        $this->mismatch('data.partner_account_tenant','Partner accounts match partner tenant','partner_accounts','partner_id','partners');
        $this->mismatch('data.inventory_item_sku_tenant','Inventory assets match SKU tenant','inventory_items','inventory_sku_id','inventory_skus');
        $locationMismatch=DB::table('inventory_items as i')->join('inventory_locations as l','l.id','=','i.current_location_id')->whereNotNull('i.current_location_id')->whereColumn('i.tenant_id','<>','l.tenant_id')->count();
        $this->add('data.inventory_location_tenant','isolation',$locationMismatch===0?'pass':'fail','critical','Inventory assets match current-location tenant',"mismatch_count={$locationMismatch}",'Repair inventory location ownership before release.');
    }

    private function checkBillingReconciliation(): void
    {
        $paymentSums=DB::table('payment_allocations')->select('payment_id',DB::raw('SUM(amount) AS allocated'))->groupBy('payment_id');
        $overPayment=DB::table('payments as p')->joinSub($paymentSums,'a',fn($j)=>$j->on('a.payment_id','=','p.id'))->whereColumn('a.allocated','>','p.amount')->count();
        $this->add('billing.payment_allocation','reconciliation',$overPayment===0?'pass':'fail','critical','Payment allocations never exceed payment amount',"violations={$overPayment}",'Repair payment allocations and re-run invoice reconciliation.');
        $badInvoices=DB::table('invoices')->whereColumn('paid_amount','>','total')->orWhereColumn('balance_due','>','total')->count();
        $this->add('billing.invoice_totals','reconciliation',$badInvoices===0?'pass':'fail','critical','Invoice paid/balance fields stay within total',"violations={$badInvoices}",'Recalculate affected invoices from payment allocations.');
    }

    private function checkPartnerReconciliation(): void
    {
        $dupes=DB::table('partner_commission_entries')->select('tenant_id','idempotency_key')->groupBy('tenant_id','idempotency_key')->havingRaw('COUNT(*) > 1')->get()->count();
        $this->add('partner.commission_idempotency','reconciliation',$dupes===0?'pass':'fail','high','Partner commission idempotency keys are unique in data',"duplicates={$dupes}",'Merge duplicate commission entries and preserve one canonical ledger entry.');
        $negative=DB::table('partner_commission_entries')->where('amount','<',0)->count();
        $this->add('partner.commission_amount','reconciliation',$negative===0?'pass':'fail','high','Partner commission amounts are non-negative',"violations={$negative}",'Correct invalid commission ledger rows.');
    }

    private function checkInventoryReconciliation(): void
    {
        $negative=DB::table('inventory_balances')->where('quantity_on_hand','<',0)->orWhere('quantity_reserved','<',0)->orWhereColumn('quantity_reserved','>','quantity_on_hand')->count();
        $this->add('inventory.balance_integrity','reconciliation',$negative===0?'pass':'fail','critical','Inventory balances are non-negative and reservations are valid',"violations={$negative}",'Reconcile ledger movements and stock opname before release.');
        $dupes=DB::table('inventory_items')->whereNotNull('serial_number')->where('serial_number','<>','')->select('tenant_id','serial_number')->groupBy('tenant_id','serial_number')->havingRaw('COUNT(*) > 1')->get()->count();
        $this->add('inventory.serial_unique','reconciliation',$dupes===0?'pass':'fail','high','Serialized inventory has no duplicate SN inside a tenant',"duplicates={$dupes}",'Resolve duplicated serial numbers before go-live.');
        $badInstalled=DB::table('inventory_items')->where('status','assigned_customer')->whereNull('assigned_customer_service_id')->count();
        $this->add('inventory.install_assignment','reconciliation',$badInstalled===0?'pass':'fail','high','Installed customer assets have a customer service assignment',"violations={$badInstalled}",'Repair installed asset ownership.');
    }

    private function checkRuntimeHardening(bool $strictProduction): void
    {
        $production=app()->environment('production');
        $shouldFail=$strictProduction || $production;
        $checks=[
            ['runtime.debug',!(bool)config('app.debug'),'APP_DEBUG is disabled'],
            ['runtime.force_https',(bool)config('jaringanku.force_https'),'FORCE_HTTPS is enabled'],
            ['runtime.secure_cookie',(bool)config('session.secure'),'Secure session cookies are enabled'],
            ['runtime.health_token',filled(config('jaringanku.health_token')),'Health readiness token is configured'],
        ];
        foreach($checks as [$key,$ok,$title]){
            $status=$ok?'pass':($shouldFail?'fail':'warn');
            $this->add($key,'hardening',$status,$shouldFail?'high':'medium',$title,$ok?'Configured.':'Not enabled in current environment.',$shouldFail?'Enable this control in .env.production.':'Expected warning for local development.');
        }
        $radius=(string)config('jaringanku.radius_client_network','disabled');
        $broad=in_array(trim($radius),['0.0.0.0/0','::/0','0.0.0.0'],true);
        $this->add('runtime.radius_scope','hardening',$broad?($shouldFail?'fail':'warn'):'pass','critical','RADIUS client network is not open to the whole Internet',"RADIUS_CLIENT_NETWORK={$radius}",'Whitelist only real NAS/MikroTik IP/CIDR.');
        $private=(bool)config('jaringanku.webhook_allow_private_networks'); $http=(bool)config('jaringanku.webhook_allow_insecure_http');
        $unsafe=$private||$http;
        $this->add('runtime.webhook_ssrf','hardening',$unsafe?($shouldFail?'fail':'warn'):'pass','high','Webhook production SSRF controls are strict',"allow_private=".($private?'true':'false')."; allow_http=".($http?'true':'false'),'Disable private-network and insecure HTTP webhook targets in production.');
        $mikrotikCidrs=array_values(array_filter(array_map('trim',explode(',',implode(',',(array)config('jaringanku.mikrotik_allowed_cidrs',[]))))));
        $invalidCidrs=array_values(array_filter($mikrotikCidrs,function($cidr){[$network,$prefix]=array_pad(explode('/',$cidr,2),2,null);$limit=str_contains((string)$network,':')?128:32;return $prefix===null||filter_var($network,FILTER_VALIDATE_IP)===false||!ctype_digit($prefix)||(int)$prefix<0||(int)$prefix>$limit;}));
        $broadMikrotik=array_values(array_filter($mikrotikCidrs,fn($cidr)=>in_array($cidr,['0.0.0.0/0','::/0'],true)));
        $mikrotikSafe=$mikrotikCidrs!==[]&&$invalidCidrs===[]&&$broadMikrotik===[];
        $this->add('runtime.mikrotik_egress_allowlist','hardening',$mikrotikSafe?'pass':($shouldFail?'fail':'warn'),'critical','MikroTik REST targets are restricted to an explicit CIDR allowlist','configured='.implode(',',$mikrotikCidrs).'; invalid='.implode(',',$invalidCidrs).'; broad='.implode(',',$broadMikrotik),'Set MIKROTIK_ALLOWED_CIDRS to the exact router ranges. Do not use 0.0.0.0/0 or ::/0.');
    }

    private function checkSourceManifest(): void
    {
        $manifest=base_path('RELEASE-SHA256SUMS.txt');
        $ok=is_file($manifest) && filesize($manifest)>0;
        $this->add('release.source_manifest','release',$ok?'pass':'fail','critical','Release checksum manifest is packaged',$ok?'RELEASE-SHA256SUMS.txt present.':'Manifest missing.','Use the official cumulative release package and run verify-release.');
    }
}
