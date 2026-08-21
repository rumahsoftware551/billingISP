<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SecurityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(Request $request, SecurityEventService $security)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        $maxAttempts = max(1, (int) config('jaringanku.login_max_attempts', 5));
        $decay = max(30, (int) config('jaringanku.login_decay_seconds', 120));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $security->record('auth.rate_limited', 'warning', [
                'email_hash' => hash('sha256', Str::lower($credentials['email'])),
                'retry_after' => RateLimiter::availableIn($key),
            ], $request);

            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'status' => 'active'], true)) {
            RateLimiter::hit($key, $decay);
            $security->record('auth.failed', 'warning', [
                'email_hash' => hash('sha256', Str::lower($credentials['email'])),
                'attempts' => RateLimiter::attempts($key),
            ], $request);
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        $user?->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $tenantId = $user?->tenants()->where('tenants.status', 'active')->wherePivot('status','active')->orderByDesc('tenant_memberships.is_default')->value('tenants.id');
        $security->record('auth.login', 'info', [], $request, $tenantId ? (string) $tenantId : null, $user?->id);

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request, SecurityEventService $security)
    {
        $user = $request->user();
        $user?->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $tenantId = $user?->tenants()->where('tenants.status', 'active')->wherePivot('status','active')->orderByDesc('tenant_memberships.is_default')->value('tenants.id');
        $security->record('auth.logout', 'info', [], $request, $tenantId ? (string) $tenantId : null, $user?->id);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
