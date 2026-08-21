<?php

namespace App\Services;

use App\Jobs\ProcessNetworkActionJob;
use App\Models\AutomationRun;
use App\Models\CustomerService;
use App\Models\NetworkActionOutbox;
use App\Models\ServiceSuspension;

class NetworkActionOutboxService
{
    public function queueDisconnect(
        CustomerService $service,
        ServiceSuspension $suspension,
        AutomationRun $run,
        string $source,
        ?int $actorUserId,
    ): NetworkActionOutbox {
        $key = sprintf('suspension:%d:run:%d', $suspension->id, $run->id);

        $outbox = NetworkActionOutbox::query()->firstOrCreate(
            [
                'tenant_id' => $service->tenant_id,
                'action' => 'disconnect',
                'idempotency_key' => $key,
            ],
            [
                'customer_service_id' => $service->id,
                'service_suspension_id' => $suspension->id,
                'automation_run_id' => $run->id,
                'actor_user_id' => $actorUserId,
                'status' => 'pending',
                'available_at' => now(),
                'result' => ['source' => $source],
            ],
        );

        if ($outbox->status === 'pending') {
            ProcessNetworkActionJob::dispatch($outbox->id)->afterCommit();
        }

        return $outbox;
    }
}
