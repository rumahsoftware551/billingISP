<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class WebhookUrlPolicy
{
    public function validateOrFail(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw ValidationException::withMessages(['url' => 'Webhook URL harus berupa HTTP/HTTPS URL yang valid.']);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['url' => 'Webhook URL tidak boleh mengandung username/password.']);
        }

        if (app()->environment('production') && $scheme !== 'https' && ! (bool) config('jaringanku.webhook_allow_insecure_http', false)) {
            throw ValidationException::withMessages(['url' => 'Production webhook harus menggunakan HTTPS.']);
        }

        if (app()->environment('local') || (bool) config('jaringanku.webhook_allow_private_networks', false)) {
            return;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost')) {
            throw ValidationException::withMessages(['url' => 'Webhook ke localhost/private network diblokir.']);
        }

        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            foreach (gethostbynamel($host) ?: [] as $address) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            throw ValidationException::withMessages(['url' => 'Hostname webhook tidak dapat di-resolve.']);
        }

        foreach ($addresses as $address) {
            $public = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) {
                throw ValidationException::withMessages(['url' => 'Webhook ke private/reserved IP diblokir.']);
            }
        }
    }
}
