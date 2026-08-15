<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationOutbox;
use App\Models\NotificationTemplate;
use App\Support\CurrentTenant;

class NotificationService
{
    public function queueTemplate(string $code, string $recipient, array $variables = [], ?string $channel = null, array $payload = []): NotificationOutbox
    {
        $template = NotificationTemplate::query()->where('code', $code)->where('enabled', true)->firstOrFail();
        $subject = $template->subject ? $this->render($template->subject, $variables) : null;
        $body = $this->render($template->body, $variables);

        return $this->queue(
            $channel ?: $template->channel,
            $recipient,
            $subject,
            $body,
            ['template_code' => $template->code, 'variables' => $variables, ...$payload],
            $template->id,
        );
    }

    public function queue(string $channel, string $recipient, ?string $subject, string $body, array $payload = [], ?int $templateId = null): NotificationOutbox
    {
        $tenantId = app(CurrentTenant::class)->id();
        $notification = NotificationOutbox::create([
            'tenant_id' => $tenantId,
            'notification_template_id' => $templateId,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'payload' => $payload,
            'status' => 'pending',
            'available_at' => now(),
        ]);

        $notification->forceFill(['status' => 'queued'])->save();
        try {
            SendNotificationJob::dispatch($notification->id);
        } catch (\Throwable $e) {
            $notification->forceFill(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 4000)])->save();
            throw $e;
        }

        return $notification->fresh();
    }

    private function render(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{'.$key.'}}'] = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
        }
        return strtr($template, $replace);
    }
}
