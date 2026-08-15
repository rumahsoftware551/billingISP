<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityEventService
{
    public function record(
        string $type,
        string $severity = 'info',
        ?array $context = null,
        ?Request $request = null,
        ?string $tenantId = null,
        ?int $userId = null,
    ): SecurityEvent {
        $request ??= app()->bound('request') ? request() : null;

        return SecurityEvent::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?? $request?->user()?->id,
            'type' => $type,
            'severity' => $severity,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
            'context' => $this->sanitize($context ?? []),
        ]);
    }

    private function sanitize(array $context): array
    {
        foreach (['password', 'secret', 'token', 'authorization'] as $key) {
            unset($context[$key]);
        }
        return $context;
    }
}
