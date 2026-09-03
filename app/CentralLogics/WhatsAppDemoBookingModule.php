<?php

namespace App\CentralLogics;

use App\CentralLogics\WhatsAppCloudApi;

/**
 * Post demo-booking WhatsApp UTILITY templates.
 * Credentials: Admin → WhatsApp Messaging (business_settings whatsapp_messaging),
 * then WhatsApp OTP settings, then env — never hard-code tokens in this file.
 *
 * Templates: mentorkhoj_util_demo_{neet|jee|tech|ai} (overridable in admin)
 */
class WhatsAppDemoBookingModule
{
    private const DEFAULT_TEMPLATES = [
        'neet' => 'mentorkhoj_util_demo_neet',
        'jee' => 'mentorkhoj_util_demo_jee',
        'tech' => 'mentorkhoj_util_demo_tech',
        'ai' => 'mentorkhoj_util_demo_ai',
    ];

    private const PROGRAMS = [
        'neet' => 'NEET Mentorship Free Demo',
        'jee' => 'JEE Mentorship Free Demo',
        'tech' => 'Tech Mentorship Free Demo',
        'ai' => 'AI/ML Mentorship Free Demo',
    ];

    // Public PNG must be reachable by Meta (404 headers cause silent delivery failure after accept)
    private const DEFAULT_HEADER = 'https://www.mentorkhoj.com/icon.png';

    /**
     * Load API details from admin form (whatsapp_messaging).
     *
     * @return array{enabled:bool,phone_number_id:?string,access_token:?string,api_version:string,template_language:string,header_image_url:string,templates:array<string,string>}
     */
    public static function messagingConfig(): array
    {
        $msg = Helpers::get_business_settings('whatsapp_messaging');
        $otp = Helpers::get_business_settings('whatsapp_otp_verification');

        $phoneNumberId = null;
        $accessToken = null;
        $enabled = false;
        $apiVersion = 'v21.0';
        $language = 'en';
        $header = self::DEFAULT_HEADER;
        $templates = self::DEFAULT_TEMPLATES;

        if (is_array($msg) && !empty($msg['phone_number_id']) && !empty($msg['access_token'])) {
            $phoneNumberId = $msg['phone_number_id'];
            $accessToken = $msg['access_token'];
            $enabled = ((int) ($msg['status'] ?? 0)) === 1;
            $apiVersion = $msg['api_version'] ?? 'v21.0';
            $language = $msg['template_language'] ?? 'en';
            $header = $msg['header_image_url'] ?? self::DEFAULT_HEADER;
            if (!empty($msg['templates']) && is_array($msg['templates'])) {
                $templates = array_merge($templates, $msg['templates']);
            }
        } elseif (is_array($otp) && !empty($otp['phone_number_id']) && !empty($otp['access_token'])) {
            // Fallback: reuse OTP Meta credentials if messaging form not filled
            $phoneNumberId = $otp['phone_number_id'];
            $accessToken = $otp['access_token'];
            $enabled = true;
            $language = $otp['template_language'] ?? env('WHATSAPP_TEMPLATE_LANGUAGE', 'en');
        } else {
            $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
            $accessToken = env('WHATSAPP_ACCESS_TOKEN') ?: env('META_ACCESS_TOKEN');
            $enabled = (bool) ($phoneNumberId && $accessToken);
            $language = env('WHATSAPP_TEMPLATE_LANGUAGE', 'en');
            $header = env('WHATSAPP_DEMO_HEADER_IMAGE', self::DEFAULT_HEADER);
        }

        return [
            'enabled' => $enabled && $phoneNumberId && $accessToken,
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
            'api_version' => $apiVersion ?: 'v21.0',
            'template_language' => $language ?: 'en',
            'header_image_url' => $header ?: self::DEFAULT_HEADER,
            'templates' => $templates,
        ];
    }

    public static function verticalKey(?string $vertical, ?string $category = null): string
    {
        $raw = strtolower(trim(($vertical ?? '') . ' ' . ($category ?? '')));
        if (str_contains($raw, 'neet')) {
            return 'neet';
        }
        if (str_contains($raw, 'jee') || str_contains($raw, 'iit')) {
            return 'jee';
        }
        if (str_contains($raw, 'tech') || str_contains($raw, 'sde') || str_contains($raw, 'engine')) {
            return 'tech';
        }
        if (str_contains($raw, 'ai') || str_contains($raw, 'ml') || str_contains($raw, 'genai')) {
            return 'ai';
        }
        $key = strtolower(trim((string) ($vertical ?: $category ?: 'neet')));

        return isset(self::DEFAULT_TEMPLATES[$key]) ? $key : 'neet';
    }

    public static function templateForVertical(?string $vertical, ?string $category = null): string
    {
        $cfg = self::messagingConfig();
        $key = self::verticalKey($vertical, $category);

        return $cfg['templates'][$key] ?? self::DEFAULT_TEMPLATES[$key];
    }

    public static function programLabel(?string $vertical, ?string $categoryLabel = null): string
    {
        if ($categoryLabel && trim($categoryLabel) !== '') {
            return trim($categoryLabel) . ' Free Demo';
        }

        return self::PROGRAMS[self::verticalKey($vertical, $categoryLabel)];
    }

    private static function sanitize(string $text, int $max = 60): string
    {
        $text = preg_replace('/[\n\t\r]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;

        return mb_substr(trim($text), 0, $max);
    }

    private static function formatMobile(string $phone): string
    {
        $d = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (strlen($d) === 10) {
            return '+91 ' . $d;
        }
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            return '+' . substr($d, 0, 2) . ' ' . substr($d, 2);
        }

        return $phone !== '' ? $phone : 'not provided';
    }

    /**
     * @return array{status:string,template:string,message?:string}
     */
    public static function sendDemoBooked(
        string $phone,
        string $name,
        string $bookingRef,
        ?string $vertical = null,
        ?string $category = null,
        ?string $email = null,
        ?string $categoryLabel = null,
    ): array {
        $cfg = self::messagingConfig();
        $template = self::templateForVertical($vertical, $category);

        if (!$cfg['enabled']) {
            return [
                'status' => 'not_configured',
                'template' => $template,
                'message' => 'Fill Admin → WhatsApp Messaging (Phone Number ID + Access Token + Enabled)',
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($to === '') {
            return ['status' => 'error', 'template' => $template, 'message' => 'Invalid phone'];
        }
        if (strlen($to) === 10) {
            $to = '91' . $to;
        }

        $customerName = self::sanitize($name !== '' ? $name : 'there', 60);
        $program = self::sanitize(self::programLabel($vertical, $categoryLabel ?: $category), 60);
        $ref = self::sanitize($bookingRef, 40);
        $emailText = self::sanitize($email && trim($email) !== '' ? $email : 'not provided', 60);
        $mobileText = self::sanitize(self::formatMobile($phone), 30);

        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => ['link' => $cfg['header_image_url']],
                    ],
                ],
            ],
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $customerName],
                    ['type' => 'text', 'text' => $program],
                    ['type' => 'text', 'text' => $ref],
                    ['type' => 'text', 'text' => $emailText],
                    ['type' => 'text', 'text' => $mobileText],
                ],
            ],
        ];

        $result = WhatsAppCloudApi::send($to, [
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $cfg['template_language']],
                'components' => $components,
            ],
        ], 'demo', 'Demo booked: ' . $template, 'template');
        $result['template'] = $template;

        return $result;
    }

    /**
     * Session time confirmed — no mentor personal details in the template.
     *
     * @return array{status:string,template:string,message?:string}
     */
    public static function sendSessionConfirmed(
        string $phone,
        string $firstName,
        string $date,
        string $time
    ): array {
        $cfg = self::messagingConfig();
        $template = $cfg['templates']['session_confirmed'] ?? 'mentorkhoj_util_session_confirmed';

        if (!$cfg['enabled']) {
            return [
                'status' => 'not_configured',
                'template' => $template,
                'message' => 'WhatsApp messaging not enabled',
            ];
        }

        $to = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($to === '') {
            return ['status' => 'error', 'template' => $template, 'message' => 'Invalid phone'];
        }
        if (strlen($to) === 10) {
            $to = '91' . $to;
        }

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => self::sanitize($firstName !== '' ? $firstName : 'there', 40)],
                    ['type' => 'text', 'text' => self::sanitize($date, 40)],
                    ['type' => 'text', 'text' => self::sanitize($time, 40)],
                    ['type' => 'text', 'text' => '+91 7366939888 / +91 9102695888'],
                ],
            ],
        ];

        $result = WhatsAppCloudApi::send($to, [
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $cfg['template_language']],
                'components' => $components,
            ],
        ], 'session', 'Session confirmed: ' . $template, 'template');
        $result['template'] = $template;

        return $result;
    }
}
