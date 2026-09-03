<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\WhatsAppCloudApi;
use App\CentralLogics\WhatsAppDemoBookingModule;
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
        $windowOpen = false;

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
                $windowOpen = WhatsAppCloudApi::isCustomerWindowOpen($activeWaId);
            }
        }

        $cfg = WhatsAppDemoBookingModule::messagingConfig();
        $templateOptions = [
            'followup' => (string) ($settings['templates']['followup'] ?? 'mentorkhoj_util_followup'),
            'neet' => (string) ($cfg['templates']['neet'] ?? 'mentorkhoj_util_demo_neet'),
            'jee' => (string) ($cfg['templates']['jee'] ?? 'mentorkhoj_util_demo_jee'),
            'tech' => (string) ($cfg['templates']['tech'] ?? 'mentorkhoj_util_demo_tech'),
            'ai' => (string) ($cfg['templates']['ai'] ?? 'mentorkhoj_util_demo_ai'),
            'session_confirmed' => (string) ($cfg['templates']['session_confirmed'] ?? 'mentorkhoj_util_session_confirmed'),
        ];

        return view('admin-views.whatsapp-messaging.edit', [
            'settings' => $settings,
            'threads' => $threads,
            'messages' => $messages,
            'activeWaId' => $activeWaId,
            'webhookUrl' => url('/api/v1/whatsapp/webhook'),
            'tableReady' => $tableReady,
            'windowOpen' => $windowOpen,
            'templateOptions' => $templateOptions,
            'defaultSendMode' => $windowOpen ? 'text' : 'template',
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'send_mode' => 'required|in:text,template',
            'body' => 'nullable|string|max:4096',
            'template_key' => 'nullable|string|max:64',
            'template_name' => 'nullable|string|max:120',
            'param1' => 'nullable|string|max:500',
            'param2' => 'nullable|string|max:500',
            'param3' => 'nullable|string|max:500',
            'param4' => 'nullable|string|max:500',
            'param5' => 'nullable|string|max:500',
        ]);

        $waId = WhatsAppCloudApi::normalizeWaId($data['phone']);
        $mode = $data['send_mode'];

        if ($mode === 'text') {
            $body = trim((string) ($data['body'] ?? ''));
            if ($body === '') {
                return redirect()
                    ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                    ->with('error', 'Enter a text message, or switch to Template to message anytime.');
            }

            $result = WhatsAppCloudApi::sendText($data['phone'], $body, 'admin');
            $waId = $result['wa_id'] ?? $waId;

            if (($result['status'] ?? '') === 'success') {
                return redirect()
                    ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                    ->with('success', 'Text message sent.');
            }

            $message = $result['message'] ?? 'Send failed';
            if (stripos($message, '24') !== false || stripos($message, 're-engage') !== false || stripos($message, 'outside') !== false) {
                $message = 'Free-form text is only allowed within 24 hours of the customer messaging you. Switch Send mode to Template to message anytime.';
            }

            return redirect()
                ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                ->with('error', $message);
        }

        $settings = $this->loadSettings();
        $cfg = WhatsAppDemoBookingModule::messagingConfig();
        $key = (string) ($data['template_key'] ?? 'followup');
        $customName = trim((string) ($data['template_name'] ?? ''));

        $map = [
            'followup' => (string) ($settings['templates']['followup'] ?? 'mentorkhoj_util_followup'),
            'neet' => (string) ($cfg['templates']['neet'] ?? 'mentorkhoj_util_demo_neet'),
            'jee' => (string) ($cfg['templates']['jee'] ?? 'mentorkhoj_util_demo_jee'),
            'tech' => (string) ($cfg['templates']['tech'] ?? 'mentorkhoj_util_demo_tech'),
            'ai' => (string) ($cfg['templates']['ai'] ?? 'mentorkhoj_util_demo_ai'),
            'session_confirmed' => (string) ($cfg['templates']['session_confirmed'] ?? 'mentorkhoj_util_session_confirmed'),
            'custom' => $customName,
        ];

        $templateName = $map[$key] ?? $customName;
        if ($templateName === '') {
            return redirect()
                ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                ->with('error', 'Choose a template or enter a custom template name.');
        }

        $params = array_values(array_filter([
            trim((string) ($data['param1'] ?? '')),
            trim((string) ($data['param2'] ?? '')),
            trim((string) ($data['param3'] ?? '')),
            trim((string) ($data['param4'] ?? '')),
            trim((string) ($data['param5'] ?? '')),
        ], static fn ($v) => $v !== ''));

        // Follow-up / free-text style: one body variable = message box
        if ($key === 'followup' || $key === 'custom') {
            $freeText = trim((string) ($data['body'] ?? ''));
            if ($params === [] && $freeText !== '') {
                $params = [$freeText];
            }
            if ($params === []) {
                return redirect()
                    ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                    ->with('error', 'Enter the message text (maps to template body {{1}}).');
            }
            $result = WhatsAppCloudApi::sendTemplate($data['phone'], $templateName, $params, 'admin');
        } elseif (in_array($key, ['neet', 'jee', 'tech', 'ai'], true)) {
            $name = $params[0] ?? 'there';
            $result = WhatsAppDemoBookingModule::sendDemoBooked(
                $data['phone'],
                $name,
                'ADMIN-' . strtoupper(substr(uniqid(), -6)),
                $key,
                $key,
                $params[1] ?? null,
                strtoupper($key)
            );
        } elseif ($key === 'session_confirmed') {
            $result = WhatsAppCloudApi::sendTemplate(
                $data['phone'],
                $templateName,
                [
                    $params[0] ?? 'there',
                    $params[1] ?? now()->format('d M Y'),
                    $params[2] ?? now()->format('h:i A'),
                    $params[3] ?? '+91 7366939888 / +91 9102695888',
                ],
                'admin'
            );
        } else {
            $result = WhatsAppCloudApi::sendTemplate($data['phone'], $templateName, $params, 'admin');
        }

        $waId = $result['wa_id'] ?? $waId;
        if (($result['status'] ?? '') === 'success') {
            return redirect()
                ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
                ->with('success', 'Template sent (works anytime, not limited to 24 hours).');
        }

        return redirect()
            ->route('admin.whatsapp-messaging.edit', ['wa_id' => $waId])
            ->with('error', $result['message'] ?? 'Template send failed. Confirm the template is APPROVED in Meta.');
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
            'template_followup' => 'nullable|string|max:120',
        ]);

        $prev = $this->loadSettings();

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
                'session_confirmed' => trim((string) ($prev['templates']['session_confirmed'] ?? 'mentorkhoj_util_session_confirmed'))
                    ?: 'mentorkhoj_util_session_confirmed',
                'followup' => trim((string) ($data['template_followup'] ?? ($prev['templates']['followup'] ?? 'mentorkhoj_util_followup')))
                    ?: 'mentorkhoj_util_followup',
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

    /** @return array<string, mixed> */
    private function loadSettings(): array
    {
        $row = DB::table('business_settings')->where('key', self::SETTINGS_KEY)->first();
        $settings = $row && $row->value ? json_decode($row->value, true) : [];

        return is_array($settings) ? $settings : [];
    }
}
