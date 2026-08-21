<?php
namespace App\Console\Commands;

use App\Services\ReleaseAcceptanceService;
use Illuminate\Console\Command;

class Phase15SecurityAuditCommand extends Command
{
    protected $signature='jaringanku:phase15-security-audit {--strict : Enforce production hardening even outside production} {--no-persist : Do not save the audit run}';
    protected $description='Run security, isolation, billing, partner, inventory, and release integrity audit.';

    public function handle(ReleaseAcceptanceService $audit): int
    {
        $result=$audit->run(!$this->option('no-persist'),null,(bool)$this->option('strict'),'Phase 15 CLI security audit');
        foreach($result['findings'] as $f){$line=strtoupper($f['status']).' '.$f['check_key'].' — '.$f['title']; if($f['status']==='fail')$this->error($line); elseif($f['status']==='warn')$this->warn($line); else $this->line('[PASS] '.$f['check_key'].' — '.$f['title']);}
        $s=$result['summary'];$this->newLine();$this->info("Audit total={$s['total']} pass={$s['passed']} warn={$s['warning']} fail={$s['failed']}");
        if($s['failed']>0){return self::FAILURE;}
        $this->info('PHASE 15 SECURITY AUDIT PASSED'); return self::SUCCESS;
    }
}
