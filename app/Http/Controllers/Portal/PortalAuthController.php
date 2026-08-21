<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerPortalAccount;
use App\Models\CustomerPortalLoginEvent;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthController extends Controller
{
    public function create(string $tenantSlug): Response
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->where('status', 'active')->firstOrFail();
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        return Inertia::render('Portal/Auth/Login', [
            'portalTenant' => $tenant->only('id', 'name', 'slug'),
        ]);
    }

    public function store(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->where('status', 'active')->firstOrFail();
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));
        $data = $request->validate([
            'identity' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $identity = trim((string) $data['identity']);
        $rateKey = 'portal-login|'.$tenant->id.'|'.Str::lower($identity).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $this->event($tenant->id, null, null, 'rate_limited', $request, ['identity_hash' => hash('sha256', Str::lower($identity))]);
            throw ValidationException::withMessages([
                'identity' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($rateKey).' detik.',
            ]);
        }

        $account = CustomerPortalAccount::query()
            ->with('customer:id,tenant_id,customer_number,name,email,status,deleted_at')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where(function ($q) use ($identity) {
                $q->whereRaw('LOWER(email) = ?', [Str::lower($identity)])
                    ->orWhereHas('customer', fn ($customer) => $customer->where('customer_number', $identity));
            })
            ->first();

        if (! $account || ! $account->customer || $account->customer->trashed() || ! $account->passwordMatches((string) $data['password'])) {
            RateLimiter::hit($rateKey, 120);
            $this->event($tenant->id, $account?->id, $account?->customer_id, 'failed', $request, ['identity_hash' => hash('sha256', Str::lower($identity))]);
            throw ValidationException::withMessages(['identity' => 'ID pelanggan/email atau password salah.']);
        }

        RateLimiter::clear($rateKey);
        $request->session()->regenerate();
        $request->session()->put('portal_account_id', $account->id);
        $request->session()->put('portal_tenant_id', (string) $tenant->id);
        $account->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->event($tenant->id, $account->id, $account->customer_id, 'login', $request);

        if ($account->must_change_password) {
            return redirect()->route('portal.profile', ['tenantSlug' => $tenantSlug])->with('success', 'Silakan ganti password portal sebelum melanjutkan.');
        }
        return redirect()->intended(route('portal.dashboard', ['tenantSlug' => $tenantSlug]));
    }

    public function destroy(Request $request, string $tenantSlug): RedirectResponse
    {
        $accountId = (int) $request->session()->get('portal_account_id', 0);
        $tenantId = (string) $request->session()->get('portal_tenant_id', '');
        $this->event($tenantId, $accountId ?: null, null, 'logout', $request);
        $request->session()->forget(['portal_account_id', 'portal_tenant_id']);
        $request->session()->regenerateToken();
        return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
    }

    private function event(string $tenantId, ?int $accountId, ?int $customerId, string $event, Request $request, array $meta = []): void
    {
        if ($tenantId === '') return;
        CustomerPortalLoginEvent::query()->create([
            'tenant_id' => $tenantId,
            'customer_portal_account_id' => $accountId,
            'customer_id' => $customerId,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
