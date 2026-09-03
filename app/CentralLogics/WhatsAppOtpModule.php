<?php

namespace App\CentralLogics;

use App\CentralLogics\WhatsAppCloudApi;
use Illuminate\Support\Facades\Log;

class WhatsAppOtpModule
{
    public static function isEnabled(): bool
    {
        $config = Helpers::get_business_settings('whatsapp_otp_verification');

        return is_array($config) && (int) ($config['status'] ?? 0) === 1;
    }

    public static function getConfig(): array
    {
        $config = Helpers::get_business_settings('whatsapp_otp_verification');

        return is_array($config) ? $config : [];
    }

    /**
     * @return 'success'|'error'|'not_configured'
     */
    public static function send($receiver, $otp): string
    {
        if (!self::isEnabled()) {
            return 'not_configured';
        }

        $config = self::getConfig();
        $provider = $config['provider'] ?? 'meta';

        if ($provider === 'meta') {
            return self::sendViaMeta($receiver, (string) $otp, $config);
        }

        return 'not_configured';
    }

    /**
     * @return 'success'|'error'|'not_configured'
     */
    private static function sendViaMeta(string $receiver, string $otp, array $config): string
    {
        $phoneNumberId = $config['phone_number_id'] ?? null;
        $accessToken = $config['access_token'] ?? null;
        $templateName = $config['template_name'] ?? 'mentorkhoj_otp';
        $templateLanguage = $config['template_language'] ?? 'en';
        $includeCopyCodeButton = (int) ($config['include_copy_code_button'] ?? 1) === 1;

        if (!$phoneNumberId || !$accessToken) {
            return 'not_configured';
        }

        $to = preg_replace('/[^0-9]/', '', $receiver);
        if ($to === '') {
            return 'error';
        }

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $otp],
                ],
            ],
        ];

        if ($includeCopyCodeButton) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $otp],
                ],
            ];
        }

        try {
            $result = WhatsAppCloudApi::send($to, [
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $templateLanguage],
                    'components' => $components,
                ],
            ], 'otp', 'OTP template sent', 'template');

            if (($result['status'] ?? '') === 'success') {
                return 'success';
            }

            Log::error('WhatsApp OTP send failed', [
                'message' => $result['message'] ?? 'unknown',
            ]);
        } catch (\Exception $exception) {
            Log::error('WhatsApp OTP exception', ['message' => $exception->getMessage()]);
        }

        return 'error';
    }
}
