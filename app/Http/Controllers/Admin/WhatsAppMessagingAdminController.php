<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\WhatsAppCloudApi;
use App\Model\WhatsApp\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separate admin section: WhatsApp Cloud API inbox + demo credentials.
 *
 * Saves business_settings key: whatsapp_messaging
 */
class WhatsAppMessagingAdminController extends Controller
{
    public const SETTINGS_KEY = 'whatsapp_messaging';

    public function edit(Request $request)
    {
        $row = DB::table('business_settings')->where('key', self::SETTINGS_KEY)->first();
        $settings = $row && $row->value ? json_decode($row->value, true) : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $threads = collect();
        $messages = collect();
        $activeWaId = WhatsAppCloudApi::normalizeWaId((string) $request->query('wa_id', ''));
        $tableReady = Schema::hasTable('whatsapp_messages');

        if ($tableReady) {
            $threads = WhatsAppMessage::query()
                ->selectRaw('wa_id, MAX(id) as last_id, MAX(occurred_at) as last_at, MAX(contact_name) as contact_name')
                ->groupBy('wa_id')
                ->orderByDesc('last_at')
                ->orderByDesc('last_id')
                ->limit(80)
                ->get();

            if ($activeWaId !== '') {
                $messages = WhatsAppMessage::query()
                    ->where('wa_id', $activeWaId)
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->limit(400)
                    ->get();
            }
        }

        return view('admin-views.whatsapp-messaging.edit', [
            'settings' => $settings,
            'threads' => $threads,
            'messages' => $messages,
            'activeWaId' => $activeWaId,
            'webhookUrl' => url('/api/v1/whatsapp/webhook'),
            'tableReady' => $tableReady,
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'body' => 'required|string|max:4096',
        ]);

        $result = WhatsAppCloudApi::sendText($data['phone'], $data['body'], 'admin');
        $waId = $result['wa_id'] ?? WhatsAppCloudApi::normalizeWaId($data['phone']);

        if (($result['status'] ?? '') === 'success') {
            return redirect()
                ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                ->with('success', 'Message sent.');
        }

        $message = $result['message'] ?? 'Send failed';
        if (stripos($message, '24') !== false || stripos($message, 're-engage') !== false) {
            $message .= ' Free-form text only works within 24 hours of the customer messaging Mentorkhoj.';
        }

        return redirect()
            ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
            ->with('error', $message);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'status' => 'nullable',
            'phone_number_id' => 'nullable|string|max:64',
            'access_token' => 'nullable|string|max:2000',
            'waba_id' => 'nullable|string|max:64',
            'api_version' => 'nullable|string|max:16',
            'template_language' => 'nullable|string|max:16',
            'header_image_url' => 'nullable|string|max:500',
            'display_phone' => 'nullable|string|max:40',
            'business_name' => 'nullable|string|max:120',
            'template_neet' => 'nullable|string|max:120',
            'template_jee' => 'nullable|string|max:120',
            'template_tech' => 'nullable|string|max:120',
            'template_ai' => 'nullable|string|max:120',
        ]);

        $existing = DB::table('business_settings')->where('key', self::SETTINGS_KEY)->first();
        $prev = $existing && $existing->value ? json_decode($existing->value, true) : [];
        if (!is_array($prev)) {
            $prev = [];
        }

        $token = trim((string) ($data['access_token'] ?? ''));
        if ($token === '') {
            $token = (string) ($prev['access_token'] ?? '');
        }

        $payload = [
            'status' => $request->has('status') ? 1 : 0,
            'provider' => 'meta',
            'phone_number_id' => trim((string) ($data['phone_number_id'] ?? '')) ?: '1247043131821693',
            'access_token' => $token,
            'waba_id' => trim((string) ($data['waba_id'] ?? '')),
            'api_version' => trim((string) ($data['api_version'] ?? 'v21.0')) ?: 'v21.0',
            'template_language' => trim((string) ($data['template_language'] ?? 'en')) ?: 'en',
            'header_image_url' => trim((string) ($data['header_image_url'] ?? ''))
                ?: 'https://www.mentorkhoj.com/icon.png',
            'display_phone' => trim((string) ($data['display_phone'] ?? '')) ?: '+91 91026 95888',
            'business_name' => trim((string) ($data['business_name'] ?? 'MentorKhoj')),
            'templates' => [
                'neet' => trim((string) ($data['template_neet'] ?? 'mentorkhoj_util_demo_neet')) ?: 'mentorkhoj_util_demo_neet',
                'jee' => trim((string) ($data['template_jee'] ?? 'mentorkhoj_util_demo_jee')) ?: 'mentorkhoj_util_demo_jee',
                'tech' => trim((string) ($data['template_tech'] ?? 'mentorkhoj_util_demo_tech')) ?: 'mentorkhoj_util_demo_tech',
                'ai' => trim((string) ($data['template_ai'] ?? 'mentorkhoj_util_demo_ai')) ?: 'mentorkhoj_util_demo_ai',
                'session_confirmed' => trim((string) ($data['template_session_confirmed'] ?? ($prev['templates']['session_confirmed'] ?? 'mentorkhoj_util_session_confirmed')))
                    ?: 'mentorkhoj_util_session_confirmed',
            ],
        ];

        DB::table('business_settings')->updateOrInsert(
            ['key' => self::SETTINGS_KEY],
            [
                'value' => json_encode($payload),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'WhatsApp messaging settings saved. Demo sends will use these values.');
    }
}
