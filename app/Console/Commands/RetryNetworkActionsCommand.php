<?php

namespace App\Console\Commands;

use App\Jobs\ProcessNetworkActionJob;
use App\Models\NetworkActionOutbox;
use Illuminate\Console\Command;

class RetryNetworkActionsCommand extends Command
{
    protected $signature = 'jaringanku:network-action-retry
        {--limit=100 : Maximum actions to dispatch}
        {--include-failed : Explicitly requeue dead-letter actions after operator review}';

    protected $description = 'Requeue pending or stale network actions without changing service state.';

    public function handle(): int
    {
        $stale = NetworkActionOutbox::query()->withoutGlobalScopes()
            ->where('status', 'processing')
            ->where('locked_at', '<=', now()->subMinutes(15))
            ->update(['status' => 'pending', 'locked_at' => null, 'available_at' => now(), 'updated_at' => now()]);

        $retriedFailed = 0;
        if ((bool) $this->option('include-failed')) {
            $retriedFailed = NetworkActionOutbox::query()->withoutGlobalScopes()
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'available_at' => now(), 'updated_at' => now()]);
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $ids = NetworkActionOutbox::query()->withoutGlobalScopes()
            ->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            ProcessNetworkActionJob::dispatch((int) $id);
        }

        $this->info("Network action retry: stale={$stale}, retried_failed={$retriedFailed}, dispatched={$ids->count()}");
        return self::SUCCESS;
    }
}
