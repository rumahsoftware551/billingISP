<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationOutbox;
use App\Models\NotificationTemplate;
use App\Models\SecurityEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\SystemHealthService;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index(SystemHealthService $health)
    {
        $tenantId = app(CurrentTenant::class)->id();

        $securityEvents = SecurityEvent::query()->latest('id');
        if (! request()->user()?->is_platform_admin) {
            $securityEvents->where('tenant_id', $tenantId);
        }

        return Inertia::render('System/Index', [
            'health' => $health->summary(),
            'templates' => NotificationTemplate::query()->orderBy('code')->get(),
            'notifications' => NotificationOutbox::query()->latest('id')->limit(20)->get(),
            'webhooks' => WebhookEndpoint::query()->latest('id')->get(),
            'deliveries' => WebhookDelivery::query()->with('endpoint:id,name')->latest('id')->limit(20)->get(),
            'securityEvents' => $securityEvents->limit(20)->get(),
            'auditLogs' => AuditLog::query()->where('tenant_id', $tenantId)->with('user:id,name,email')->latest('id')->limit(30)->get(),
            'production' => [
                'app_env' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'force_https' => (bool) config('jaringanku.force_https'),
                'secure_cookie' => (bool) config('session.secure'),
                'trusted_proxies_configured' => filled(config('jaringanku.trusted_proxies')),
            ],
        ]);
    }

    public function testNotification(Request $request, NotificationService $notifications, AuditService $audit)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['log', 'email'])],
            'recipient' => ['required', 'string', 'max:255'],
        ]);

        $notification = $notifications->queue(
            $data['channel'],
            $data['recipient'],
            'Jaringanku System Test',
            'Notification engine Jaringanku berhasil menerima test message.',
            ['source' => 'system-test'],
        );

        $audit->record('system.notification_test', NotificationOutbox::class, $notification->id, null, [
            'channel' => $notification->channel,
            'recipient' => $notification->recipient,
        ]);

        return back()->with('success', 'Notification test masuk ke queue.');
    }
}
