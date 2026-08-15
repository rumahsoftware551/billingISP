<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class ProductionPreflightCommand extends Command
{
    protected $signature = 'jaringanku:production-preflight';
    protected $description = 'Fail-fast checks before a Jaringanku v1.0 production release.';

    public function handle(): int
    {
        $errors = [];
        if (app()->environment('production')) {
            if (config('app.debug')) $errors[] = 'APP_DEBUG must be false.';
            if (! config('jaringanku.force_https')) $errors[] = 'FORCE_HTTPS must be true.';
            if (! str_starts_with((string)config('app.url'), 'https://')) $errors[] = 'APP_URL must use HTTPS.';
            if (! config('session.secure')) $errors[] = 'SESSION_SECURE_COOKIE must be true.';
            if (filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) $errors[] = 'SEED_DEMO_DATA must be false.';
            if (blank(config('jaringanku.health_token'))) $errors[] = 'HEALTH_TOKEN must be configured.';
        }
        try { DB::select('select 1'); } catch (\Throwable $e) { $errors[] = 'Database unavailable: '.$e->getMessage(); }
        try { Redis::connection()->ping(); } catch (\Throwable $e) { $errors[] = 'Redis unavailable: '.$e->getMessage(); }
        foreach (['tenants','tenant_subscriptions','platform_plans','release_records'] as $table) if (! Schema::hasTable($table)) $errors[] = "Missing {$table}.";
        if ($errors) { foreach ($errors as $e) $this->error($e); return self::FAILURE; }
        $this->info('JARINGANKU PRODUCTION PREFLIGHT PASSED');
        return self::SUCCESS;
    }
}
