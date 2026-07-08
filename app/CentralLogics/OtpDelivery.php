<?php

namespace App\CentralLogics;

use Modules\Gateways\Traits\SmsGateway;

class OtpDelivery
{
    /**
     * @return array{message: string, channel: 'whatsapp'|'sms'}
     */
    public static function sendPhoneOtp(string $phone, $otp): array
    {
        if (WhatsAppOtpModule::isEnabled()) {
            return [
                'message' => WhatsAppOtpModule::send($phone, $otp),
                'channel' => 'whatsapp',
            ];
        }

        if (addon_published_status('Gateways')) {
            return [
                'message' => SmsGateway::send($phone, $otp),
                'channel' => 'sms',
            ];
        }

        return [
            'message' => SMS_module::send($phone, $otp),
            'channel' => 'sms',
        ];
    }
}
