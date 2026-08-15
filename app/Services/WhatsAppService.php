<?php

namespace App\Services;

use App\Models\NotificationOutbox;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function setting(): WhatsAppSetting
    {
        return WhatsAppSetting::query()->firstOrCreate([], [
            'provider' => 'meta_cloud',
            'mode' => app()->environment('local') ? 'log' : 'cloud',
            'enabled' => app()->environment('local'),
            'graph_version' => 'v26.0',
            'default_country_code' => '62',
            'template_map' => [],
        ]);
    }

    public function send(NotificationOutbox $notification): array
    {
        $setting = $this->setting();
        if (! $setting->enabled) {
            throw new \RuntimeException('WhatsApp integration belum diaktifkan.');
        }
        $recipient = $this->normalizePhone($notification->recipient, $setting->default_country_code ?: '62');
        $log = WhatsAppMessageLog::query()->firstOrCreate(
            ['notification_outbox_id' => $notification->id],
            ['recipient' => $recipient, 'provider' => $setting->provider, 'status' => 'sending']
        );

        try {
            if ($setting->mode === 'log') {
                Log::info('Jaringanku WhatsApp LOG mode', ['to' => $recipient, 'body' => $notification->body, 'payload' => $notification->payload]);
                $response = ['messages' => [['id' => 'wamid.mock.'.hash('sha256', (string) $notification->id)]]];
            } else {
                if ((string) $setting->access_token === '' || ! $setting->phone_number_id) {
                    throw new \RuntimeException('WhatsApp access token / phone number ID belum lengkap.');
                }
                $payload = $this->messagePayload($notification, $setting, $recipient);
                $version = preg_match('/^v\d+\.\d+$/', (string) $setting->graph_version) ? $setting->graph_version : 'v26.0';
                $url = 'https://graph.facebook.com/'.$version.'/'.rawurlencode((string) $setting->phone_number_id).'/messages';
                $response = Http::withToken((string) $setting->access_token)->acceptJson()->asJson()->timeout(20)->retry(2, 750)
                    ->post($url, $payload)->throw()->json();
            }
            $messageId = data_get($response, 'messages.0.id');
            $log->forceFill(['status' => 'sent', 'provider_message_id' => $messageId, 'response' => $response, 'last_error' => null])->save();
            return $response;
        } catch (\Throwable $e) {
            $log->forceFill(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 4000)])->save();
            throw $e;
        }
    }

    private function messagePayload(NotificationOutbox $notification, WhatsAppSetting $setting, string $recipient): array
    {
        $templateCode = data_get($notification->payload, 'template_code');
        $mapped = $templateCode ? data_get($setting->template_map ?: [], $templateCode) : null;
        if ($mapped) {
            $variables = array_values(data_get($notification->payload, 'variables', []));
            $components = $variables === [] ? [] : [[
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $variables),
            ]];
            return [
                'messaging_product' => 'whatsapp', 'to' => $recipient, 'type' => 'template',
                'template' => ['name' => $mapped, 'language' => ['code' => $setting->template_language ?: 'id'], 'components' => $components],
            ];
        }
        return ['messaging_product' => 'whatsapp', 'to' => $recipient, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $notification->body]];
    }

    public function normalizePhone(string $phone, string $countryCode = '62'): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') throw new \RuntimeException('Nomor WhatsApp kosong/tidak valid.');
        if (str_starts_with($digits, '0')) $digits = $countryCode.substr($digits, 1);
        return $digits;
    }
}
