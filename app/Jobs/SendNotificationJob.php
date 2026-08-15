<?php

namespace App\Jobs;

use App\Models\NotificationOutbox;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\WhatsAppService;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public int $notificationId) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $notification = NotificationOutbox::query()->withoutGlobalScopes()->findOrFail($this->notificationId);
        if ($notification->status === 'sent') {
            return;
        }

        $tenant = Tenant::query()->findOrFail($notification->tenant_id);
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $notification->forceFill([
            'status' => 'sending',
            'attempts' => $notification->attempts + 1,
            'last_error' => null,
        ])->save();

        if ($notification->channel === 'log') {
            Log::info('Jaringanku notification', [
                'tenant' => $tenant->slug,
                'recipient' => $notification->recipient,
                'subject' => $notification->subject,
                'body' => $notification->body,
            ]);
        } elseif ($notification->channel === 'email') {
            Mail::raw($notification->body, function ($message) use ($notification) {
                $message->to($notification->recipient);
                if ($notification->subject) {
                    $message->subject($notification->subject);
                }
            });
        } elseif ($notification->channel === 'whatsapp') {
            app(WhatsAppService::class)->send($notification);
        } else {
            throw new \RuntimeException('Notification channel belum didukung: '.$notification->channel);
        }

        $notification->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
    }

    public function failed(Throwable $e): void
    {
        NotificationOutbox::query()->withoutGlobalScopes()->whereKey($this->notificationId)->update([
            'status' => 'failed',
            'last_error' => mb_substr($e->getMessage(), 0, 4000),
            'updated_at' => now(),
        ]);
    }
}
