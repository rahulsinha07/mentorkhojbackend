<?php

namespace App\Console\Commands;

use App\CentralLogics\WhatsAppOtpModule;
use Illuminate\Console\Command;

class TestWhatsAppOtp extends Command
{
    protected $signature = 'mentorkhoj:test-whatsapp-otp {phone : E.164 phone e.g. +919876543210}';

    protected $description = 'Send a test OTP via WhatsApp Cloud API using current admin settings';

    public function handle(): int
    {
        if (!WhatsAppOtpModule::isEnabled()) {
            $this->error('WhatsApp OTP is not enabled. Run: php artisan mentorkhoj:setup-whatsapp-otp');
            return self::FAILURE;
        }

        $otp = (string) random_int(100000, 999999);
        $phone = $this->argument('phone');
        $this->info("Sending test OTP {$otp} to {$phone}...");

        $result = WhatsAppOtpModule::send($phone, $otp);

        if ($result === 'success') {
            $this->info('WhatsApp API accepted the message. Check the phone.');
            return self::SUCCESS;
        }

        $this->error("Send failed: {$result}");
        $this->line('Check storage/logs/laravel.log for Graph API error details.');
        return self::FAILURE;
    }
}
