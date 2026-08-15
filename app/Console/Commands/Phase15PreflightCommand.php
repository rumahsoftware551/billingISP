<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Phase15PreflightCommand extends Command
{
    protected $signature='jaringanku:phase15-preflight';
    protected $description='Validate the Phase 15 stable baseline inside this or any newer cumulative release.';

    public function handle(): int
    {
        foreach(['release_acceptance_runs','security_audit_findings'] as $table){if(!Schema::hasTable($table)){$this->error("Missing table: {$table}");return self::FAILURE;}}
        foreach(['platform.release','platform.release.audit','version','portal.dashboard','partner.dashboard','inventory.dashboard'] as $route){if(!Route::has($route)){$this->error("Missing route: {$route}");return self::FAILURE;}}
        $version = trim((string) config('jaringanku.version'));
        $channel = trim((string) config('jaringanku.release_channel'));
        if ($version === '' || !version_compare($version, '1.1.0', '>=')) {
            $this->error("JARINGANKU_VERSION must satisfy the Phase 15 baseline (>= 1.1.0). Current: {$version}");
            return self::FAILURE;
        }
        if (!in_array($channel, ['stable', 'development'], true)) {
            $this->error("RELEASE_CHANNEL must be stable or development for cumulative regression. Current: {$channel}");
            return self::FAILURE;
        }
        if ($version === '1.1.0' && $channel !== 'stable') {
            $this->error('Jaringanku v1.1.0 itself must use RELEASE_CHANNEL=stable.');
            return self::FAILURE;
        }
        if(!is_file(base_path('RELEASE-SHA256SUMS.txt'))){$this->error('RELEASE-SHA256SUMS.txt missing.');return self::FAILURE;}
        $this->info('PHASE 15 BASELINE RELEASE PREFLIGHT PASSED');
        $this->line("Current cumulative release: {$version} / {$channel}");
        return self::SUCCESS;
    }
}
