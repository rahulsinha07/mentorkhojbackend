<?php

namespace App\CentralLogics;

use App\Model\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudApi
{
    public static function normalizeWaId(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        $digits = ltrim($digits, '0');
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }

    /**
     * @return array{status:string,wamid?:string,message?:string,wa_id?:string}
     */
    public static function sendText(string $phone, string $body, string $source = 'admin'): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['status' => 'error', 'message' => 'Message is empty'];
        }

        return self::send($phone, [
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => mb_substr($body, 0, 4096),
            ],
        ], $source, $body, 'text');
    }

    /**
     * @param array<string, mixed> $payload Graph message fields besides messaging_product/to
     * @return array{status:string,wamid?:string,message?:string,wa_id?:string,template?:string}
     */
    public static function send(string $phone, array $payload, string $source, string $displayBody, string $type = 'text'): array
    {
        $cfg = WhatsAppDemoBookingModule::messagingConfig();
        $to = self::normalizeWaId($phone);
        if ($to === '') {
            return ['status' => 'error', 'message' => 'Invalid phone'];
        }
        if (!$cfg['enabled']) {
            return ['status' => 'not_configured', 'message' => 'WhatsApp messaging is not enabled', 'wa_id' => $to];
        }

        $version = $cfg['api_version'] ?: 'v21.0';
        if (!str_starts_with($version, 'v')) {
            $version = 'v' . $version;
        }

        try {
            $response = Http::withToken($cfg['access_token'])
                ->timeout(30)
                ->post("https://graph.facebook.com/{$version}/{$cfg['phone_number_id']}/messages", array_merge([
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                ], $payload));

            $json = $response->json();
            if ($response->successful()) {
                $wamid = (string) data_get($json, 'messages.0.id', '');
                self::logOutbound($to, $displayBody, $type, $source, $wamid !== '' ? $wamid : null, $json, 'sent');

                return ['status' => 'success', 'wamid' => $wamid, 'wa_id' => $to];
            }

            $message = (string) (data_get($json, 'error.message') ?: mb_substr($response->body(), 0, 300));
            Log::error('WhatsApp Cloud API send failed', [
                'source' => $source,
                'http' => $response->status(),
                'error' => data_get($json, 'error'),
            ]);

            return ['status' => 'error', 'message' => $message, 'wa_id' => $to];
        } catch (\Throwable $e) {
            Log::error('WhatsApp Cloud API exception', ['source' => $source, 'message' => $e->getMessage()]);

            return ['status' => 'error', 'message' => $e->getMessage(), 'wa_id' => $to];
        }
    }

    public static function logOutbound(
        string $waId,
        string $body,
        string $type,
        string $source,
        ?string $wamid,
        mixed $payload,
        string $status = 'sent'
    ): void {
        try {
            WhatsAppMessage::query()->create([
                'wa_id' => $waId,
                'contact_name' => null,
                'direction' => 'out',
                'wamid' => $wamid ?: null,
                'type' => $type,
                'body' => $body,
                'status' => $status,
                'source' => $source,
                'payload' => is_array($payload) ? $payload : ['raw' => $payload],
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp outbound log failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function ingestWebhook(array $payload): void
    {
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                if (!is_array($value)) {
                    continue;
                }
                $contacts = $value['contacts'] ?? [];
                $nameByWa = [];
                foreach ($contacts as $contact) {
                    $id = (string) ($contact['wa_id'] ?? '');
                    if ($id !== '') {
                        $nameByWa[$id] = (string) data_get($contact, 'profile.name', '');
                    }
                }

                foreach (($value['messages'] ?? []) as $message) {
                    self::storeInbound($message, $nameByWa);
                }
                foreach (($value['statuses'] ?? []) as $status) {
                    self::applyStatus($status);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, string> $nameByWa
     */
    private static function storeInbound(array $message, array $nameByWa): void
    {
        $wamid = (string) ($message['id'] ?? '');
        $from = self::normalizeWaId((string) ($message['from'] ?? ''));
        if ($from === '') {
            return;
        }

        $type = (string) ($message['type'] ?? 'unknown');
        $body = self::extractInboundBody($message, $type);
        $ts = isset($message['timestamp']) ? (int) $message['timestamp'] : time();

        $attrs = [
            'wa_id' => $from,
            'contact_name' => $nameByWa[$from] ?? ($nameByWa[$message['from'] ?? ''] ?? null),
            'direction' => 'in',
            'type' => $type,
            'body' => $body,
            'status' => 'received',
            'source' => 'webhook',
            'payload' => $message,
            'occurred_at' => now()->setTimestamp($ts),
        ];

        try {
            if ($wamid !== '') {
                WhatsAppMessage::query()->updateOrCreate(
                    ['wamid' => $wamid],
                    $attrs
                );

                return;
            }
            WhatsAppMessage::query()->create($attrs + ['wamid' => null]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp inbound store failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function extractInboundBody(array $message, string $type): string
    {
        if ($type === 'text') {
            return (string) data_get($message, 'text.body', '');
        }
        if ($type === 'button') {
            return (string) (data_get($message, 'button.text') ?: data_get($message, 'button.payload', '[button]'));
        }
        if ($type === 'interactive') {
            return (string) (
                data_get($message, 'interactive.button_reply.title')
                ?: data_get($message, 'interactive.list_reply.title')
                ?: '[interactive]'
            );
        }
        if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
            $caption = (string) data_get($message, $type . '.caption', '');

            return $caption !== '' ? $caption : '[' . $type . ']';
        }
        if ($type === 'location') {
            return '[location]';
        }
        if ($type === 'contacts') {
            return '[contact]';
        }

        return '[' . $type . ']';
    }

    /**
     * @param array<string, mixed> $status
     */
    private static function applyStatus(array $status): void
    {
        $wamid = (string) ($status['id'] ?? '');
        if ($wamid === '') {
            return;
        }
        $row = WhatsAppMessage::query()->where('wamid', $wamid)->first();
        if (!$row) {
            return;
        }
        $row->status = (string) ($status['status'] ?? $row->status);
        if (!empty($status['errors'])) {
            $err = (string) data_get($status, 'errors.0.title', 'failed');
            $row->status = 'failed';
            $row->body = trim((string) $row->body . "\n[" . $err . ']');
        }
        $row->save();
    }
}
