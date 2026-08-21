<?php

namespace App\Jobs;

use App\Models\CustomerService;
use App\Models\NetworkActionOutbox;
use App\Models\Tenant;
use App\Services\RadiusCoaService;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessNetworkActionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $outboxId) {}

    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }

    public function handle(RadiusCoaService $coa): void
    {
        $outbox = NetworkActionOutbox::query()->withoutGlobalScopes()->findOrFail($this->outboxId);
        if (in_array($outbox->status, ['succeeded', 'cancelled'], true)) {
            return;
        }

        $claimed = NetworkActionOutbox::query()->withoutGlobalScopes()
            ->whereKey($outbox->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'attempts' => DB::raw('attempts + 1'),
                'locked_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $outbox->refresh();
        $tenant = Tenant::query()->findOrFail($outbox->tenant_id);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        try {
            $service = CustomerService::query()->whereKey($outbox->customer_service_id)->first();
            if (! $service || $service->status !== 'suspended') {
                $this->cancel($outbox, 'Aksi jaringan dibatalkan karena layanan tidak lagi suspended.');
                return;
            }

            if ($outbox->action !== 'disconnect') {
                $this->cancel($outbox, 'Jenis aksi jaringan tidak didukung.');
                return;
            }

            $result = $coa->disconnectAllForService($service, $outbox->actor_user_id);
            if ((int) $result['failed'] > 0) {
                throw new \RuntimeException('Disconnect RADIUS belum lengkap: '.implode('; ', $result['errors']));
            }

            $outbox->forceFill([
                'status' => 'succeeded',
                'completed_at' => now(),
                'locked_at' => null,
                'last_error' => null,
                'result' => [...($outbox->result ?? []), 'disconnect' => $result],
            ])->save();
        } catch (Throwable $e) {
            $outbox->forceFill([
                'status' => 'pending',
                'locked_at' => null,
                'available_at' => now()->addSeconds(60),
                'last_error' => mb_substr($e->getMessage(), 0, 4000),
            ])->save();
            throw $e;
        } finally {
            app()->forgetInstance(CurrentTenant::class);
        }
    }

    public function failed(Throwable $e): void
    {
        NetworkActionOutbox::query()->withoutGlobalScopes()->whereKey($this->outboxId)->update([
            'status' => 'failed',
            'locked_at' => null,
            'last_error' => mb_substr($e->getMessage(), 0, 4000),
            'updated_at' => now(),
        ]);
    }

    private function cancel(NetworkActionOutbox $outbox, string $reason): void
    {
        $outbox->forceFill([
            'status' => 'cancelled',
            'completed_at' => now(),
            'locked_at' => null,
            'last_error' => $reason,
        ])->save();
    }
}
