<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    public function record(
        string $event,
        ?string $auditableType = null,
        string|int|null $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $source = 'web',
        ?Request $request = null,
        ?int $userId = null,
    ): AuditLog {
        $request ??= app()->bound('request') ? request() : null;
        $tenantId = app()->bound(CurrentTenant::class) ? app(CurrentTenant::class)->id() : null;

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId ?? $request?->user()?->id,
            'event' => $event,
            'source' => $source,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId !== null ? (string) $auditableId : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
            'request_id' => $request?->attributes->get('request_id'),
        ]);
    }
}
