<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupWhatsAppOtp extends Command
{
    protected $signature = 'mentorkhoj:setup-whatsapp-otp
        {--phone-number-id= : Meta WhatsApp Phone Number ID}
        {--access-token= : Meta system user or permanent access token}
        {--template-name=mentorkhoj_otp : Approved authentication template name}
        {--template-language=en : Template language code}
        {--disable-phone-verification : Turn off legacy SMS/Firebase phone verification}
        {--dry-run : Preview settings without saving}';

    protected $description = 'Enable WhatsApp OTP verification via Meta Cloud API (admin DB settings)';

    public function handle(): int
    {
        $phoneNumberId = $this->option('phone-number-id') ?: env('WHATSAPP_PHONE_NUMBER_ID');
        $accessToken = $this->option('access-token') ?: env('WHATSAPP_ACCESS_TOKEN');
        $templateName = (string) ($this->option('template-name') ?: env('WHATSAPP_OTP_TEMPLATE_NAME', 'mentorkhoj_otp'));
        $templateLanguage = (string) ($this->option('template-language') ?: env('WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'en'));
        $includeCopyCode = (int) (env('WHATSAPP_OTP_INCLUDE_COPY_CODE_BUTTON', 1)) === 1;
        $dryRun = (bool) $this->option('dry-run');

        if (!$phoneNumberId || !$accessToken) {
            $this->error('Missing credentials. Set WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN in .env');
            $this->line('  or pass --phone-number-id=... --access-token=...');
            return self::FAILURE;
        }

        $payload = [
            'status' => 1,
            'provider' => 'meta',
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
            'template_name' => $templateName,
            'template_language' => $templateLanguage,
            'include_copy_code_button' => $includeCopyCode ? 1 : 0,
        ];

        $this->info('WhatsApp OTP settings to save:');
        $this->table(
            ['Key', 'Value'],
            collect($payload)->map(fn ($v, $k) => [
                $k,
                in_array($k, ['access_token'], true) ? str_repeat('*', min(12, strlen((string) $v))) : $v,
            ])->values()->all()
        );

        if ($dryRun) {
            $this->warn('Dry run — nothing written.');
            return self::SUCCESS;
        }

        DB::table('business_settings')->updateOrInsert(['key' => 'whatsapp_otp_verification'], [
            'value' => json_encode($payload),
        ]);

        if ($this->option('disable-phone-verification')) {
            DB::table('business_settings')->updateOrInsert(['key' => 'phone_verification'], ['value' => '0']);
            DB::table('business_settings')->updateOrInsert(['key' => 'firebase_otp_verification'], [
                'value' => json_encode(['status' => 0, 'web_api_key' => '']),
            ]);
            $this->info('Disabled phone_verification and firebase_otp_verification.');
        }

        if (env('APP_MODE') !== 'live') {
            $this->warn('APP_MODE is not "live" — OTP codes will be fixed to 123456 for testing.');
        }

        $this->info('WhatsApp OTP enabled. Verify: GET /api/v1/config → whatsapp_otp.status = 1');
        return self::SUCCESS;
    }
}
