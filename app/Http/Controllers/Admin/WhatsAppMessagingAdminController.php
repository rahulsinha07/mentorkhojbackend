<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Separate admin section: WhatsApp Cloud API for demo / messaging
 * (independent from WhatsApp OTP login settings).
 *
 * Saves business_settings key: whatsapp_messaging
 * WhatsAppDemoBookingModule reads this first when sending.
 */
class WhatsAppMessagingAdminController extends Controller
{
    public const SETTINGS_KEY = 'whatsapp_messaging';

    public function edit()
    {
        $row = DB::table('business_settings')->where('key', self::SETTINGS_KEY)->first();
        $settings = $row && $row->value ? json_decode($row->value, true) : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        return view('admin-views.whatsapp-messaging.edit', [
            'settings' => $settings,
        ]);
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

        // Keep previous token if field left blank
        $token = trim((string) ($data['access_token'] ?? ''));
        if ($token === '') {
            $token = (string) ($prev['access_token'] ?? '');
        }

        $payload = [
            'status' => $request->has('status') ? 1 : 0,
            'provider' => 'meta',
            'phone_number_id' => trim((string) ($data['phone_number_id'] ?? '')),
            'access_token' => $token,
            'waba_id' => trim((string) ($data['waba_id'] ?? '')),
            'api_version' => trim((string) ($data['api_version'] ?? 'v21.0')) ?: 'v21.0',
            'template_language' => trim((string) ($data['template_language'] ?? 'en')) ?: 'en',
            'header_image_url' => trim((string) ($data['header_image_url'] ?? ''))
                ?: 'https://www.mentorkhoj.com/icon.png',
            'display_phone' => trim((string) ($data['display_phone'] ?? '')),
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
