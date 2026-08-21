<?php

use App\Services\RadiusPacketClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('jaringanku:accounting-smoke {--leave-open : Leave the synthetic accounting session online after Interim-Update}', function () {
    $client = app(RadiusPacketClient::class);
    $secret = (string) config('jaringanku.radius_shared_secret', '');
    if ($secret === '') {
        $this->error('RADIUS_SHARED_SECRET kosong.');
        return 1;
    }

    $username = 'phase3-demo';
    $nasIp = '192.0.2.44'; // TEST-NET-1, never routed on the public Internet.
    $framedIp = '198.51.100.44'; // TEST-NET-2.
    $sessionId = 'phase04-smoke-'.Str::lower(Str::random(10));

    // Keep this smoke test idempotent. Only reserved documentation addresses are removed.
    DB::table('radacct')
        ->where('username', $username)
        ->where('nasipaddress', $nasIp)
        ->delete();

    $send = function (string $status, array $extra = []) use ($client, $secret, $username, $nasIp, $framedIp, $sessionId) {
        $lines = [
            'User-Name = "'.$username.'"',
            'Acct-Session-Id = "'.$sessionId.'"',
            'Acct-Status-Type = '.$status,
            'NAS-IP-Address = '.$nasIp,
            'NAS-Identifier = "jaringanku-phase04-smoke"',
            'NAS-Port = 1',
            'NAS-Port-Id = "ether-smoke"',
            'NAS-Port-Type = Ethernet',
            'Service-Type = Framed-User',
            'Framed-Protocol = PPP',
            'Framed-IP-Address = '.$framedIp,
            'Calling-Station-Id = "02:00:00:00:04:04"',
            'Called-Station-Id = "jaringanku-smoke"',
            'Acct-Authentic = RADIUS',
            ...$extra,
        ];

        return $client->sendLines('radius', 1813, 'acct', $secret, $lines);
    };

    $this->info('Sending Accounting-Start...');
    $start = $send('Start');
    $this->line($start['output']);
    if ($start['response_code'] !== 'Accounting-Response') {
        $this->error('Accounting-Start tidak menerima Accounting-Response.');
        return 2;
    }

    usleep(300_000);
    $this->info('Sending Interim-Update...');
    $interim = $send('Interim-Update', [
        'Acct-Session-Time = 60',
        'Acct-Input-Octets = 1048576',
        'Acct-Output-Octets = 5242880',
        'Acct-Input-Packets = 1200',
        'Acct-Output-Packets = 3200',
    ]);
    $this->line($interim['output']);
    if ($interim['response_code'] !== 'Accounting-Response') {
        $this->error('Interim-Update tidak menerima Accounting-Response.');
        return 3;
    }

    if (! $this->option('leave-open')) {
        usleep(300_000);
        $this->info('Sending Accounting-Stop...');
        $stop = $send('Stop', [
            'Acct-Session-Time = 120',
            'Acct-Input-Octets = 2097152',
            'Acct-Output-Octets = 10485760',
            'Acct-Input-Packets = 2400',
            'Acct-Output-Packets = 6400',
            'Acct-Terminate-Cause = User-Request',
        ]);
        $this->line($stop['output']);
        if ($stop['response_code'] !== 'Accounting-Response') {
            $this->error('Accounting-Stop tidak menerima Accounting-Response.');
            return 4;
        }
    }

    usleep(300_000);
    $row = DB::table('radacct')->where('acctsessionid', $sessionId)->first();
    if (! $row) {
        $this->error('radacct tidak berisi session smoke test.');
        return 5;
    }

    if ($this->option('leave-open') && $row->acctstoptime !== null) {
        $this->error('Session seharusnya online, tetapi acctstoptime sudah terisi.');
        return 6;
    }
    if (! $this->option('leave-open') && $row->acctstoptime === null) {
        $this->error('Session seharusnya closed, tetapi acctstoptime masih NULL.');
        return 7;
    }

    $this->newLine();
    $this->info('PHASE 04 ACCOUNTING SMOKE TEST PASSED');
    $this->line('Session ID : '.$sessionId);
    $this->line('Username   : '.$username);
    $this->line('State      : '.($row->acctstoptime === null ? 'ONLINE' : 'STOPPED'));
    $this->line('Input      : '.(string) ($row->acctinputoctets ?? 0));
    $this->line('Output     : '.(string) ($row->acctoutputoctets ?? 0));

    return 0;
})->purpose('Send Start/Interim/Stop packets through FreeRADIUS and verify radacct.');

Artisan::command('jaringanku:radius-resync {--tenant= : Optional tenant slug}', function () {
    $projection = app(\App\Services\RadiusProjectionService::class);

    $tenantQuery = \App\Models\Tenant::query();

    if ($slug = $this->option('tenant')) {
        $tenantQuery->where('slug', $slug);
    }

    $tenants = $tenantQuery->orderBy('id')->get();

    if ($tenants->isEmpty()) {
        $this->error('Tenant tidak ditemukan.');
        return 2;
    }

    $count = 0;

    try {
        foreach ($tenants as $tenant) {
            app()->instance(
                \App\Support\CurrentTenant::class,
                new \App\Support\CurrentTenant($tenant)
            );

            \App\Models\CustomerService::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->chunkById(100, function ($services) use ($projection, &$count) {
                    foreach ($services as $service) {
                        $projection->syncService($service);
                        $count++;
                    }
                });
        }
    } finally {
        app()->forgetInstance(\App\Support\CurrentTenant::class);
    }

    $this->info("RADIUS projection resynced for {$count} active service(s).");
    return 0;
})->purpose('Rebuild radcheck/radreply projections for all active Jaringanku services.');

Artisan::command('jaringanku:billing-run {period? : Billing period in YYYY-MM} {--tenant= : Optional tenant slug}', function () {
    $periodArg = $this->argument('period') ?: now()->format('Y-m');
    try {
        $period = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $periodArg)->startOfMonth();
    } catch (\Throwable) {
        $this->error('Format periode harus YYYY-MM.');
        return 1;
    }

    $query = \App\Models\Tenant::query();
    if ($slug = $this->option('tenant')) {
        $query->where('slug', $slug);
    }
    $tenants = $query->get();
    if ($tenants->isEmpty()) {
        $this->error('Tenant tidak ditemukan.');
        return 2;
    }

    foreach ($tenants as $tenant) {
        $run = app(\App\Services\BillingEngine::class)->runForTenant($tenant, $period, null);
        $this->info(sprintf(
            '%s %s: created=%d existing=%d errors=%d',
            $tenant->slug,
            $period->format('Y-m'),
            $run->created_count,
            $run->skipped_count,
            $run->error_count
        ));
    }

    return 0;
})->purpose('Generate monthly invoices idempotently for one or all tenants.');

Artisan::command('jaringanku:billing-due-run {date? : As-of date in YYYY-MM-DD} {--tenant= : Optional tenant slug}', function () {
    $dateArg = $this->argument('date') ?: now()->format('Y-m-d');

    try {
        $asOf = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $dateArg)->startOfDay();
    } catch (\Throwable) {
        $this->error('Format tanggal harus YYYY-MM-DD.');
        return 1;
    }

    if ($asOf->format('Y-m-d') !== $dateArg) {
        $this->error('Format tanggal harus YYYY-MM-DD.');
        return 1;
    }

    $query = \App\Models\Tenant::query();
    if ($slug = $this->option('tenant')) {
        $query->where('slug', $slug);
    }

    $tenants = $query->get();
    if ($tenants->isEmpty()) {
        $this->error('Tenant tidak ditemukan.');
        return 2;
    }

    $exit = 0;
    foreach ($tenants as $tenant) {
        $run = app(\App\Services\BillingEngine::class)->runDueForTenant($tenant, $asOf, null);
        $this->info(sprintf(
            '%s %s: eligible=%d created=%d existing=%d errors=%d',
            $tenant->slug,
            $asOf->format('Y-m-d'),
            $run->eligible_count,
            $run->created_count,
            $run->skipped_count,
            $run->error_count
        ));

        if ($run->error_count > 0) {
            $exit = 3;
        }
    }

    return $exit;
})->purpose('Generate recurring invoices only for active services whose billing day is due, with catch-up after scheduler downtime.');

Artisan::command('jaringanku:payment-reconcile {--tenant= : Optional tenant slug} {--check : Detect mismatches without repairing}', function () {
    $query = \App\Models\Tenant::query();
    if ($slug = $this->option('tenant')) {
        $query->where('slug', $slug);
    }

    $tenants = $query->get();
    if ($tenants->isEmpty()) {
        $this->error('Tenant tidak ditemukan.');
        return 2;
    }

    $repair = ! (bool) $this->option('check');
    $exit = 0;

    foreach ($tenants as $tenant) {
        $stats = app(\App\Services\PaymentReconciliationService::class)->reconcileTenant($tenant, $repair);
        $this->line(sprintf(
            '%s: scanned=%d mismatches=%d repaired=%d violations=%d',
            $tenant->slug,
            $stats['scanned'],
            $stats['mismatches'],
            $stats['repaired'],
            $stats['violations']
        ));

        if ($stats['violations'] > 0 || (! $repair && $stats['mismatches'] > 0)) {
            $exit = 3;
        }
    }

    return $exit;
})->purpose('Reconcile invoice paid/balance/status fields from posted payment allocations.');

Artisan::command('jaringanku:billing-refresh', function () {
    $total = 0;
    foreach (\App\Models\Tenant::query()->get() as $tenant) {
        $total += app(\App\Services\BillingEngine::class)->refreshStatuses($tenant);
    }
    $this->info("Billing statuses refreshed. Changed={$total}");
    return 0;
})->purpose('Refresh unpaid/partial/overdue invoice statuses.');

Artisan::command('jaringanku:billing-smoke', function () {
    $tenant = \App\Models\Tenant::query()->where('slug', config('jaringanku.seed_tenant_slug', 'demo-isp'))->first();
    if (! $tenant) {
        $this->error('Demo tenant tidak ditemukan.');
        return 1;
    }
    app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));

    $service = \App\Models\CustomerService::query()->where('pppoe_username', 'phase3-demo')->first();
    if (! $service) {
        $this->warn('phase3-demo tidak ditemukan; billing smoke dilewati karena SEED_DEMO_DATA mungkin false.');
        return 0;
    }

    $period = now()->toImmutable()->startOfMonth();
    $engine = app(\App\Services\BillingEngine::class);
    $before = \App\Models\Invoice::query()->where('customer_service_id', $service->id)->whereDate('period_start', $period->toDateString())->count();
    $first = $engine->generateForService($service, $period);
    $second = $engine->generateForService($service, $period);
    $after = \App\Models\Invoice::query()->where('customer_service_id', $service->id)->whereDate('period_start', $period->toDateString())->count();

    if ($first->id !== $second->id || $after !== max(1, $before)) {
        $this->error('Billing idempotency smoke test gagal.');
        return 2;
    }
    if ((int) $first->total !== (int) $service->plan()->value('price')) {
        $this->error('Invoice total tidak sama dengan harga paket.');
        return 3;
    }

    $this->info('PHASE 05 BILLING SMOKE TEST PASSED');
    $this->line('Invoice  : '.$first->invoice_number);
    $this->line('Service  : '.$service->service_number.' / '.$service->pppoe_username);
    $this->line('Total    : Rp'.number_format($first->total, 0, ',', '.'));
    $this->line('Balance  : Rp'.number_format($first->balance_due, 0, ',', '.'));
    return 0;
})->purpose('Verify monthly invoice generation and idempotency for the local demo service.');


Artisan::command('jaringanku:automation-run {--tenant= : Optional tenant slug} {--source=scheduled : Run source label}', function () {
    $query = \App\Models\Tenant::query();
    if ($slug = $this->option('tenant')) {
        $query->where('slug', $slug);
    }
    $tenants = $query->get();
    if ($tenants->isEmpty()) {
        $this->error('Tenant tidak ditemukan.');
        return 1;
    }

    $exit = 0;
    foreach ($tenants as $tenant) {
        $run = app(\App\Services\BillingAutomationService::class)->evaluateTenant(
            $tenant,
            (string) $this->option('source'),
            null,
        );
        $this->info(sprintf(
            '%s: scanned=%d suspended=%d reactivated=%d enforced=%d skipped=%d errors=%d',
            $tenant->slug,
            $run->scanned_count,
            $run->suspended_count,
            $run->reactivated_count,
            $run->enforced_count,
            $run->skipped_count,
            $run->error_count,
        ));
        if ($run->error_count > 0) {
            $exit = 2;
        }
    }

    return $exit;
})->purpose('Evaluate overdue invoices, isolate delinquent PPPoE services, and reactivate cleared billing suspensions.');

Artisan::command('jaringanku:automation-smoke', function () {
    $tenant = \App\Models\Tenant::query()->where('slug', config('jaringanku.seed_tenant_slug', 'demo-isp'))->first();
    if (! $tenant) {
        $this->error('Demo tenant tidak ditemukan.');
        return 1;
    }
    app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));

    $service = \App\Models\CustomerService::query()->where('pppoe_username', 'phase3-demo')->first();
    if (! $service) {
        $this->warn('phase3-demo tidak ditemukan; automation smoke dilewati karena SEED_DEMO_DATA mungkin false.');
        return 0;
    }
    $cleanup = function () use ($service) {
        $invoiceIds = \App\Models\Invoice::query()
            ->where('customer_service_id', $service->id)
            ->where('billing_key', 'like', 'phase06-smoke:%')
            ->pluck('id');
        if ($invoiceIds->isEmpty()) {
            return;
        }

        $paymentIds = \Illuminate\Support\Facades\DB::table('payment_allocations')
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('payment_id');
        $runIds = \App\Models\AutomationEvent::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('automation_run_id')
            ->filter()
            ->unique();

        \App\Models\AutomationEvent::query()->whereIn('invoice_id', $invoiceIds)->delete();
        \App\Models\ServiceSuspension::query()->whereIn('invoice_id', $invoiceIds)->delete();
        \Illuminate\Support\Facades\DB::table('payment_allocations')->whereIn('invoice_id', $invoiceIds)->delete();
        if ($paymentIds->isNotEmpty()) {
            \App\Models\Payment::query()->whereIn('id', $paymentIds)->delete();
        }
        \Illuminate\Support\Facades\DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
        \App\Models\Invoice::query()->whereIn('id', $invoiceIds)->delete();
        if ($runIds->isNotEmpty()) {
            \App\Models\AutomationRun::query()->whereIn('id', $runIds)->delete();
        }
    };

    // Remove artifacts from an interrupted previous smoke run. If that run stopped
    // after suspension, restore only the suspension that is provably owned by the smoke invoice.
    $oldSmokeInvoiceIds = \App\Models\Invoice::query()
        ->where('customer_service_id', $service->id)
        ->where('billing_key', 'like', 'phase06-smoke:%')
        ->pluck('id');
    $recoverSmokeSuspension = $oldSmokeInvoiceIds->isNotEmpty()
        && \App\Models\ServiceSuspension::query()
            ->where('customer_service_id', $service->id)
            ->where('source', 'billing_automation')
            ->where('status', 'active')
            ->whereIn('invoice_id', $oldSmokeInvoiceIds)
            ->exists();
    $cleanup();
    if ($recoverSmokeSuspension && $service->fresh()->status === 'suspended') {
        $service->forceFill(['status' => 'active'])->save();
        app(\App\Services\RadiusProjectionService::class)->syncService($service->fresh(['plan', 'ipPool']));
    }
    $service->refresh();
    if ($service->status !== 'active') {
        $this->error('phase3-demo harus ACTIVE sebelum automation smoke. Status saat ini: '.$service->status);
        return 2;
    }

    $automation = app(\App\Services\BillingAutomationService::class);
    $policy = $automation->policy();
    $policy->forceFill([
        'auto_suspend' => true,
        'auto_reactivate' => true,
        'disconnect_on_suspend' => true,
    ])->save();

    $token = strtolower((string) \Illuminate\Support\Str::ulid());
    $dueAt = today()->subDays(max(0, (int) $policy->grace_days) + 2);
    $invoice = \App\Models\Invoice::create([
        'customer_id' => $service->customer_id,
        'customer_service_id' => $service->id,
        'invoice_number' => 'SMOKE-P6-'.substr($token, -8),
        'billing_key' => 'phase06-smoke:'.$token,
        'period_start' => $dueAt->copy()->startOfMonth()->toDateString(),
        'period_end' => $dueAt->copy()->endOfMonth()->toDateString(),
        'issued_at' => $dueAt->copy()->subDays(5)->toDateString(),
        'due_at' => $dueAt->toDateString(),
        'subtotal' => 1000,
        'discount' => 0,
        'tax' => 0,
        'total' => 1000,
        'paid_amount' => 0,
        'balance_due' => 1000,
        'status' => 'overdue',
        'notes' => 'Temporary Phase 06 automation smoke invoice.',
    ]);
    $invoice->items()->create([
        'description' => 'Phase 06 automation smoke item',
        'quantity' => 1,
        'unit_price' => 1000,
        'amount' => 1000,
        'meta' => ['smoke' => 'phase06'],
    ]);

    $this->info('1/4 Menjalankan overdue automation...');
    $suspend = $automation->evaluateService($service->fresh(), 'smoke', null, null);
    $service->refresh();
    if ($suspend['action'] !== 'suspended' || $service->status !== 'suspended') {
        $this->error('Service tidak berubah menjadi suspended.');
        return 3;
    }
    $cleartextStillPresent = \Illuminate\Support\Facades\DB::table('radcheck')
        ->where('username', $service->pppoe_username)
        ->where('attribute', 'Cleartext-Password')
        ->exists();
    $rejectMarkerPresent = \Illuminate\Support\Facades\DB::table('radcheck')
        ->where('username', $service->pppoe_username)
        ->where('attribute', 'Auth-Type')
        ->whereRaw('LOWER(value) = ?', ['reject'])
        ->exists();
    if ($cleartextStillPresent || ! $rejectMarkerPresent) {
        $this->error('Projection isolir RADIUS tidak valid: Cleartext-Password harus hilang dan Auth-Type := Reject harus ada.');
        return 4;
    }
    $this->info('2/4 Isolir database/RADIUS berhasil. Memastikan Access-Reject eksplisit...');
    $reject = app(\App\Services\RadiusTestService::class)->authenticate($service->pppoe_username, $service->pppoe_password);
    $this->line($reject['output']);
    if (! ($reject['rejected'] ?? false)) {
        if ($reject['ok']) {
            $this->error('PPPoE masih Access-Accept setelah isolir.');
        } elseif ($reject['timed_out'] ?? false) {
            $this->error('RADIUS tidak memberi respons. Isolir harus menghasilkan Access-Reject, bukan timeout.');
        } else {
            $this->error('RADIUS tidak menghasilkan Access-Reject setelah isolir.');
        }
        return 5;
    }

    $this->info('3/4 Posting pembayaran penuh untuk memicu auto-reactivation...');
    $payment = app(\App\Services\PaymentService::class)->postToInvoice(
        $invoice,
        1000,
        'cash',
        'PHASE06-SMOKE',
        now(),
        'Temporary automation smoke payment.',
        null,
    );
    $service->refresh();
    if ($service->status !== 'active' || $payment->getAttribute('automation_action') !== 'reactivated') {
        $this->error('Auto-reactivation setelah pembayaran gagal.');
        return 6;
    }
    if (! \Illuminate\Support\Facades\DB::table('radcheck')->where('username', $service->pppoe_username)->exists()) {
        $this->error('RADIUS credential belum dipulihkan setelah reaktivasi.');
        return 7;
    }

    $this->info('4/4 Memastikan login kembali Access-Accept...');
    $accept = app(\App\Services\RadiusTestService::class)->authenticate($service->pppoe_username, $service->pppoe_password);
    $this->line($accept['output']);
    if (! $accept['ok']) {
        $this->error('PPPoE belum Access-Accept setelah reaktivasi.');
        return 8;
    }

    $cleanup();
    $this->newLine();
    $this->info('PHASE 06 AUTOMATION SMOKE TEST PASSED');
    $this->line('Suspend   : PASS');
    $this->line('RADIUS    : Access-Reject while suspended');
    $this->line('Payment   : PASS');
    $this->line('Reactivate: PASS');
    $this->line('RADIUS    : Access-Accept after reactivation');
    return 0;
})->purpose('Verify overdue suspend, RADIUS reject, payment-triggered reactivation, and RADIUS restore.');


Artisan::command('jaringanku:reports-smoke', function () {
    $tenant = \App\Models\Tenant::query()->where('slug', config('jaringanku.seed_tenant_slug', 'demo-isp'))->first();
    if (! $tenant) {
        $this->error('Demo tenant tidak ditemukan.');
        return 1;
    }
    app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));

    $from = now()->toImmutable()->subMonths(5)->startOfMonth();
    $to = now()->toImmutable()->endOfDay();
    $reports = app(\App\Services\ReportService::class);

    $this->info('1/4 Menjalankan seluruh query analytics...');
    $report = $reports->report($from, $to);
    foreach (['summary','financial','customer_growth','service_status','plan_distribution','aging','top_outstanding','network','automation'] as $key) {
        if (! array_key_exists($key, $report)) {
            $this->error("Report key {$key} tidak tersedia.");
            return 2;
        }
    }

    $this->info('2/4 Memverifikasi rekonsiliasi outstanding...');
    $expectedOutstanding = (int) \App\Models\Invoice::query()->where('balance_due', '>', 0)->sum('balance_due');
    $agingOutstanding = (int) collect($report['aging'])->sum('amount');
    if ($expectedOutstanding !== $agingOutstanding || $expectedOutstanding !== (int) $report['summary']['outstanding']) {
        $this->error("Outstanding tidak reconcile. expected={$expectedOutstanding} aging={$agingOutstanding} summary=".$report['summary']['outstanding']);
        return 3;
    }

    $this->info('3/4 Memverifikasi dataset CSV...');
    foreach (['customers','services','invoices','outstanding','payments','sessions'] as $type) {
        $dataset = $reports->exportDataset($type, $from, $to);
        if (empty($dataset['headers']) || ! is_array($dataset['rows'])) {
            $this->error("Dataset {$type} tidak valid.");
            return 4;
        }
        $columns = count($dataset['headers']);
        foreach ($dataset['rows'] as $index => $row) {
            if (count($row) !== $columns) {
                $this->error("Dataset {$type} row {$index} memiliki jumlah kolom berbeda.");
                return 5;
            }
        }
        $this->line(sprintf('  %-12s %d row(s)', $type, count($dataset['rows'])));
    }

    $this->info('4/4 Memverifikasi audit export model...');
    $probe = \App\Models\ReportExport::create([
        'report_type' => 'reports-smoke',
        'format' => 'csv',
        'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'smoke' => true],
        'row_count' => 0,
        'exported_by' => null,
        'exported_at' => now(),
    ]);
    if (! $probe->exists || (string) $probe->tenant_id !== (string) $tenant->id) {
        $this->error('Tenant scope ReportExport gagal.');
        return 6;
    }
    $probe->delete();

    $this->newLine();
    $this->info('PHASE 07 REPORTS SMOKE TEST PASSED');
    $this->line('Outstanding : Rp'.number_format($expectedOutstanding, 0, ',', '.'));
    $this->line('Financial   : '.count($report['financial']).' month bucket(s)');
    $this->line('Online      : '.$report['network']['online_sessions'].' session(s)');
    $this->line('CSV exports : 6 dataset(s) validated');
    return 0;
})->purpose('Validate Phase 07 analytics queries, outstanding reconciliation, CSV datasets, and export audit.');


Artisan::command('jaringanku:health', function () {
    $health = app(\App\Services\SystemHealthService::class)->summary();
    $this->line('Jaringanku health: '.strtoupper($health['status']));
    foreach ($health['checks'] as $name => $check) {
        $this->line(sprintf('[%s] %-14s %s%s', $check['ok'] ? 'PASS' : 'WARN', $name, $check['message'], $check['latency_ms'] !== null ? ' ('.$check['latency_ms'].' ms)' : ''));
    }
    return $health['ready'] ? 0 : 2;
})->purpose('Check database, Redis, storage, queue/scheduler heartbeat and failed-job health.');

Artisan::command('jaringanku:phase08-smoke', function () {
    $tenant = \App\Models\Tenant::query()->where('slug', config('jaringanku.seed_tenant_slug', 'demo-isp'))->first();
    if (! $tenant) {
        $this->error('Demo tenant tidak ditemukan.');
        return 1;
    }
    app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));

    $this->info('1/5 Memverifikasi migration dan health core...');
    foreach (['notification_templates','notification_outbox','webhook_endpoints','webhook_deliveries','security_events'] as $table) {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            $this->error("Table {$table} tidak tersedia.");
            return 2;
        }
    }
    foreach (['request_id', 'source'] as $column) {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('audit_logs', $column)) {
            $this->error("audit_logs.{$column} tidak tersedia.");
            return 3;
        }
    }
    $health = app(\App\Services\SystemHealthService::class)->summary();
    if (! $health['ready']) {
        $this->error('Core readiness gagal.');
        return 4;
    }

    $this->info('2/5 Memverifikasi queue worker lewat heartbeat job...');
    \App\Jobs\QueueHeartbeatJob::dispatch();
    $queueOk = false;
    for ($i = 0; $i < 30; $i++) {
        usleep(250_000);
        if (\Illuminate\Support\Facades\Cache::get('jaringanku:queue_heartbeat')) {
            $queueOk = true;
            break;
        }
    }
    if (! $queueOk) {
        $this->error('Queue heartbeat tidak diproses. Pastikan queue container running.');
        return 5;
    }

    $this->info('3/5 Memverifikasi notification outbox + queue...');
    $notification = app(\App\Services\NotificationService::class)->queue(
        'log', 'phase08-smoke@jaringanku.local', 'Phase 08 smoke', 'Notification smoke test.', ['smoke' => true]
    );
    for ($i = 0; $i < 40; $i++) {
        usleep(250_000);
        $notification->refresh();
        if (in_array($notification->status, ['sent', 'failed'], true)) {
            break;
        }
    }
    if ($notification->status !== 'sent') {
        $this->error('Notification smoke gagal: status='.$notification->status.' error='.($notification->last_error ?: '-'));
        return 6;
    }

    $this->info('4/5 Memverifikasi signed webhook delivery end-to-end...');
    $endpoint = \App\Models\WebhookEndpoint::query()->updateOrCreate(
        ['name' => '__phase08_smoke__'],
        [
            'url' => config('jaringanku.phase08_smoke_webhook_url', 'http://nginx/api/phase8-smoke/webhook'),
            'secret' => 'phase08-smoke-secret-'.\Illuminate\Support\Str::random(24),
            'events' => ['phase08.smoke'],
            'enabled' => true,
            'timeout_seconds' => 5,
            'max_attempts' => 2,
        ]
    );
    $delivery = app(\App\Services\WebhookService::class)->emitToEndpoint($tenant, $endpoint, 'phase08.smoke', ['message' => 'hello']);
    for ($i = 0; $i < 60; $i++) {
        usleep(250_000);
        $delivery->refresh();
        if (in_array($delivery->status, ['delivered', 'failed', 'cancelled'], true)) {
            break;
        }
    }
    if ($delivery->status !== 'delivered') {
        $this->error('Webhook smoke gagal: status='.$delivery->status.' HTTP='.($delivery->response_code ?: '-').' error='.($delivery->last_error ?: '-'));
        return 7;
    }

    $this->info('5/5 Memverifikasi tenant audit log...');
    $audit = app(\App\Services\AuditService::class)->record('phase08.smoke', 'system', 'phase08', null, ['ok' => true], 'cli', null, null);
    if ((string) $audit->tenant_id !== (string) $tenant->id || $audit->source !== 'cli') {
        $this->error('Audit smoke tidak tenant-scoped.');
        return 8;
    }

    // Cleanup probes only. Production data is untouched.
    $endpoint->delete();
    $notification->delete();
    $audit->delete();

    $this->newLine();
    $this->info('PHASE 08 PRODUCTION READINESS SMOKE TEST PASSED');
    $this->line('Core health       : PASS');
    $this->line('Queue heartbeat   : PASS');
    $this->line('Notification job  : PASS');
    $this->line('Signed webhook    : PASS');
    $this->line('Tenant audit      : PASS');
    return 0;
})->purpose('Validate Phase 08 health, queue, notification, webhook, and audit foundations.');


Artisan::command('jaringanku:payment-reminders {--tenant= : Optional tenant slug}', function () {
    $query = \App\Models\Tenant::query();
    if ($slug = $this->option('tenant')) $query->where('slug', $slug);
    $queued = 0;
    foreach ($query->get() as $tenant) {
        app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));
        $notifications = app(\App\Services\PaymentNotificationService::class);
        \App\Models\Invoice::query()->with('customer')
            ->where('balance_due', '>', 0)->where('status', 'overdue')->whereDate('due_at', '<', today())
            ->orderBy('id')->chunkById(100, function ($invoices) use ($notifications, &$queued) {
                foreach ($invoices as $invoice) {
                    if (! $invoice->customer?->phone) continue;
                    $already = \App\Models\NotificationOutbox::query()
                        ->where('channel', 'whatsapp')
                        ->whereDate('created_at', today())
                        ->where('payload->template_code', 'billing.overdue')
                        ->where('payload->invoice_id', $invoice->id)
                        ->exists();
                    if ($already) continue;
                    $notifications->overdueReminder($invoice);
                    $queued++;
                }
            });
    }
    $this->info("Payment reminder queued={$queued}");
    return 0;
})->purpose('Queue at most one WhatsApp overdue reminder per invoice per day.');

Artisan::command('jaringanku:payment-expire', function () {
    $count = \App\Models\PaymentGatewayTransaction::query()
        ->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())
        ->update(['status' => 'expired', 'updated_at' => now()]);
    $this->info("Expired gateway transactions={$count}");
    return 0;
})->purpose('Mark local payment gateway sessions expired after their configured lifetime.');

Artisan::command('jaringanku:phase09-smoke', function () {
    $tenant = \App\Models\Tenant::query()->where('slug', config('jaringanku.seed_tenant_slug', 'demo-isp'))->first();
    if (! $tenant) { $this->error('Demo tenant tidak ditemukan.'); return 1; }
    app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));
    $service = \App\Models\CustomerService::query()->where('pppoe_username', 'phase3-demo')->with('customer')->first();
    if (! $service) { $this->warn('phase3-demo tidak ditemukan; Phase 09 smoke dilewati karena demo data disabled.'); return 0; }

    foreach (['payment_gateway_settings','payment_gateway_transactions','payment_gateway_events','whatsapp_settings','whatsapp_message_logs'] as $table) {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) { $this->error("Table {$table} tidak tersedia."); return 2; }
    }

    $gatewaySetting = \App\Models\PaymentGatewaySetting::query()->firstOrCreate([], ['provider'=>'mock','environment'=>'sandbox','enabled'=>true,'expiry_minutes'=>60]);
    $waSetting = \App\Models\WhatsAppSetting::query()->firstOrCreate([], ['provider'=>'meta_cloud','mode'=>'log','enabled'=>true,'graph_version'=>'v26.0','default_country_code'=>'62']);
    $oldGateway = ['provider'=>$gatewaySetting->provider,'environment'=>$gatewaySetting->environment,'enabled'=>$gatewaySetting->enabled];
    $oldWa = ['mode'=>$waSetting->mode,'enabled'=>$waSetting->enabled];
    $enabledWebhookIds = \App\Models\WebhookEndpoint::query()->where('enabled', true)->pluck('id')->all();
    if ($enabledWebhookIds) \App\Models\WebhookEndpoint::query()->whereIn('id', $enabledWebhookIds)->update(['enabled'=>false]);
    $gatewaySetting->forceFill(['provider'=>'mock','environment'=>'sandbox','enabled'=>true])->save();
    $waSetting->forceFill(['mode'=>'log','enabled'=>true])->save();

    $token = strtolower((string) \Illuminate\Support\Str::ulid());
    $invoice = null; $transaction = null; $notification = null; $paymentId = null;
    try {
        $this->info('1/4 Membuat invoice smoke sementara...');
        $invoice = \App\Models\Invoice::create([
            'customer_id'=>$service->customer_id,'customer_service_id'=>$service->id,
            'invoice_number'=>'SMOKE-P9-'.substr($token,-8),'billing_key'=>'phase09-smoke:'.$token,
            'period_start'=>today()->startOfMonth(),'period_end'=>today()->endOfMonth(),'issued_at'=>today(),'due_at'=>today()->addDays(7),
            'subtotal'=>1000,'discount'=>0,'tax'=>0,'total'=>1000,'paid_amount'=>0,'balance_due'=>1000,'status'=>'unpaid','notes'=>'Temporary Phase 09 smoke invoice.',
        ]);
        $invoice->items()->create(['description'=>'Phase 09 gateway smoke item','quantity'=>1,'unit_price'=>1000,'amount'=>1000,'meta'=>['smoke'=>'phase09']]);

        $this->info('2/4 Membuat mock QRIS transaction dan menyelesaikannya idempotently...');
        $transaction = app(\App\Services\PaymentGatewayService::class)->initiate($invoice);
        if ($transaction->provider !== 'mock' || $transaction->status !== 'pending' || ! $transaction->redirect_url) { $this->error('Mock gateway initiation gagal.'); return 3; }
        $first = app(\App\Services\PaymentGatewayNotificationService::class)->settleMock($transaction);
        $second = app(\App\Services\PaymentGatewayNotificationService::class)->settleMock($first);
        if ($first->status !== 'paid' || ! $first->payment_id || $first->payment_id !== $second->payment_id) { $this->error('Mock settlement/idempotency gagal.'); return 4; }
        $paymentId = $first->payment_id;
        $invoice->refresh();
        if ($invoice->status !== 'paid' || (int)$invoice->balance_due !== 0) { $this->error('Gateway payment tidak mem-post invoice dengan benar.'); return 5; }

        $this->info('3/4 Memverifikasi WhatsApp notification queue dalam LOG mode...');
        $notification = app(\App\Services\NotificationService::class)->queue('whatsapp', $service->customer->phone ?: '081234567890', 'Phase 09 smoke', 'WhatsApp integration smoke.', ['smoke'=>true]);
        for ($i=0;$i<40;$i++){usleep(250_000);$notification->refresh();if(in_array($notification->status,['sent','failed'],true))break;}
        $waLog = \App\Models\WhatsAppMessageLog::query()->where('notification_outbox_id',$notification->id)->first();
        if ($notification->status !== 'sent' || ! $waLog || $waLog->status !== 'sent' || ! str_starts_with((string)$waLog->provider_message_id,'wamid.mock.')) { $this->error('WhatsApp LOG mode smoke gagal.'); return 6; }

        $this->info('4/4 Memverifikasi payment gateway + notification audit state...');
        if (! \App\Models\PaymentGatewayTransaction::query()->whereKey($transaction->id)->where('status','paid')->exists()) { $this->error('Gateway transaction paid state hilang.'); return 7; }

        $this->newLine();
        $this->info('PHASE 09 PAYMENT + WHATSAPP SMOKE TEST PASSED');
        $this->line('Mock QRIS          : PASS');
        $this->line('Settlement idempotent: PASS');
        $this->line('Invoice payment    : PASS');
        $this->line('WhatsApp queue/log : PASS');
        return 0;
    } finally {
        if ($notification) $notification->delete();
        if ($invoice) \App\Models\NotificationOutbox::query()->where('payload->invoice_id', $invoice->id)->delete();
        if ($transaction) \App\Models\PaymentGatewayTransaction::query()->whereKey($transaction->id)->delete();
        if ($invoice) {
            \Illuminate\Support\Facades\DB::table('payment_allocations')->where('invoice_id',$invoice->id)->delete();
            if ($paymentId) \App\Models\Payment::query()->whereKey($paymentId)->delete();
            \Illuminate\Support\Facades\DB::table('invoice_items')->where('invoice_id',$invoice->id)->delete();
            \App\Models\Invoice::query()->whereKey($invoice->id)->delete();
        }
        $gatewaySetting->forceFill($oldGateway)->save();
        $waSetting->forceFill($oldWa)->save();
        if ($enabledWebhookIds) \App\Models\WebhookEndpoint::query()->whereIn('id', $enabledWebhookIds)->update(['enabled'=>true]);
    }
})->purpose('Validate Phase 09 mock QRIS, idempotent settlement, invoice posting, and WhatsApp queue adapter.');


Artisan::command('jaringanku:phase10-preflight', function () {
    foreach (['customer_portal_accounts','customer_portal_login_events'] as $table) {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            $this->error("Table {$table} tidak tersedia.");
            return 1;
        }
    }
    $account = new \App\Models\CustomerPortalAccount();
    $event = new \App\Models\CustomerPortalLoginEvent();
    if ($account->getTable() !== 'customer_portal_accounts' || $event->getTable() !== 'customer_portal_login_events') {
        $this->error('Model table mapping portal tidak sesuai migration.');
        return 2;
    }
    foreach (['portal.login','portal.dashboard','portal.invoices.show','portal.invoices.download','portal.receipts.download'] as $routeName) {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            $this->error("Route {$routeName} tidak terdaftar.");
            return 3;
        }
    }
    $this->info('PHASE 10 DATABASE + ROUTE PREFLIGHT PASSED');
    return 0;
})->purpose('Validate Phase 10 customer portal schema, model mappings, and routes.');

Artisan::command('jaringanku:phase10-smoke', function () {
    $tenant = \App\Models\Tenant::query()->where('slug', config('jaringanku.seed_tenant_slug', 'demo-isp'))->first();
    if (! $tenant) { $this->error('Demo tenant tidak ditemukan.'); return 1; }
    app()->instance(\App\Support\CurrentTenant::class, new \App\Support\CurrentTenant($tenant));
    $customer = \App\Models\Customer::query()->where('customer_number', 'JRG-000001')->first();
    if (! $customer) { $this->warn('Demo customer tidak ditemukan; Phase 10 smoke dilewati karena demo data disabled.'); return 0; }
    $account = \App\Models\CustomerPortalAccount::query()->where('tenant_id',$tenant->id)->where('customer_id',$customer->id)->first();
    if (! $account) { $this->error('Customer portal account demo tidak ditemukan.'); return 2; }
    $probe = new \App\Models\CustomerPortalAccount(['password' => \Illuminate\Support\Facades\Hash::make('Phase10SmokePassword!')]);
    if (! $probe->passwordMatches('Phase10SmokePassword!')) { $this->error('Portal password hashing/check gagal.'); return 3; }

    $this->info('1/4 Memverifikasi tenant/customer scope portal...');
    $foreign = \App\Models\Invoice::query()->withoutGlobalScopes()->where('tenant_id','!=',$tenant->id)->where('customer_id',$customer->id)->exists();
    if ($foreign) { $this->error('Ditemukan invoice customer ID yang sama pada tenant lain; fixture tidak aman.'); return 4; }
    $portalInvoices = \App\Models\Invoice::query()->where('customer_id',$customer->id)->get();
    if ($portalInvoices->contains(fn($invoice)=>(string)$invoice->tenant_id !== (string)$tenant->id || (int)$invoice->customer_id !== (int)$customer->id)) {
        $this->error('Portal invoice scope bocor.'); return 5;
    }

    $this->info('2/4 Memverifikasi PDF invoice generator...');
    $invoice = $portalInvoices->first();
    if (!$invoice) {
        $service = \App\Models\CustomerService::query()->where('customer_id',$customer->id)->first();
        if (!$service) { $this->error('Tidak ada service demo untuk membuat invoice smoke.'); return 6; }
        $invoice = app(\App\Services\BillingEngine::class)->generateForService($service, now()->toImmutable()->startOfMonth());
    }
    $pdf = app(\App\Services\PortalDocumentService::class)->invoicePdf($invoice);
    if (!str_starts_with($pdf, '%PDF-1.4') || !str_contains($pdf, $invoice->invoice_number)) {
        $this->error('Invoice PDF generator gagal.'); return 7;
    }

    $this->info('3/4 Memverifikasi portal URLs dan payment gateway compatibility...');
    $loginUrl = route('portal.login',['tenantSlug'=>$tenant->slug], false);
    $invoiceUrl = route('portal.invoices.show',['tenantSlug'=>$tenant->slug,'invoice'=>$invoice->id], false);
    if ($loginUrl !== '/portal/'.$tenant->slug.'/login' || !str_starts_with($invoiceUrl, '/portal/'.$tenant->slug.'/invoices/')) {
        $this->error('Portal route generation tidak sesuai.'); return 8;
    }
    $setting = \App\Models\PaymentGatewaySetting::query()->first();
    if (app()->environment('local') && (!$setting || $setting->provider !== 'mock')) {
        $this->warn('Local payment provider bukan mock; payment UI tetap valid tetapi mock settlement tidak diuji Phase 10.');
    }

    $this->info('4/4 Memverifikasi PWA assets + login audit model...');
    foreach (['manifest.webmanifest','portal-sw.js','icons/jaringanku-192.svg'] as $asset) {
        if (!is_file(public_path($asset))) { $this->error("PWA asset {$asset} tidak ditemukan."); return 9; }
    }
    $event = \App\Models\CustomerPortalLoginEvent::query()->create([
        'tenant_id'=>$tenant->id,'customer_portal_account_id'=>$account->id,'customer_id'=>$customer->id,
        'event'=>'smoke','ip_address'=>'127.0.0.1','user_agent'=>'phase10-smoke','meta'=>['ok'=>true],'created_at'=>now(),
    ]);
    if ((string)$event->tenant_id !== (string)$tenant->id) { $this->error('Portal audit event tenant mismatch.'); return 10; }
    $event->delete();

    $this->newLine();
    $this->info('PHASE 10 CUSTOMER PORTAL SMOKE TEST PASSED');
    $this->line('Portal account       : PASS');
    $this->line('Tenant/customer scope: PASS');
    $this->line('Invoice PDF          : PASS');
    $this->line('Portal routes        : PASS');
    $this->line('PWA assets           : PASS');
    return 0;
})->purpose('Validate customer portal account, tenant scoping, PDF, routes, and PWA assets.');

\Illuminate\Support\Facades\Schedule::command('jaringanku:billing-refresh')->dailyAt('00:10')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:payment-reconcile')->dailyAt('00:15')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:billing-due-run')->dailyAt('00:20')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:automation-run')->everyTenMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:payment-expire')->everyFiveMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:payment-reminders')->dailyAt('09:00')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::call(function () { \Illuminate\Support\Facades\Cache::put('jaringanku:scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(5)); })->name('jaringanku-scheduler-heartbeat')->everyMinute()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::job(new \App\Jobs\QueueHeartbeatJob())->name('jaringanku-queue-heartbeat-dispatch')->everyMinute()->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::command('jaringanku:saas-sweep')->hourly()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:partner-monthly-commission')->monthlyOn(1, '01:20')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:network-health --probe-routers')->everyFiveMinutes()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('jaringanku:network-action-retry')->everyMinute()->withoutOverlapping();
