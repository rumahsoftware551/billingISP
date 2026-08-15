<?php
namespace App\Console\Commands;

use App\Models\ReleaseRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecordReleaseCommand extends Command
{
    protected $signature = 'jaringanku:release-record {--version=1.2.0-dev} {--commit=} {--notes=}';
    protected $description = 'Record a successful Jaringanku deployment.';

    public function handle(): int
    {
        ReleaseRecord::create([
            'version'=>$this->option('version'),
            'environment'=>app()->environment(),
            'schema_version'=>DB::table('migrations')->max('migration'),
            'git_commit'=>$this->option('commit') ?: env('RELEASE_GIT_COMMIT'),
            'status'=>'deployed',
            'notes'=>$this->option('notes'),
            'deployed_at'=>now(),
        ]);
        $this->info('Release record saved.');
        return self::SUCCESS;
    }
}
