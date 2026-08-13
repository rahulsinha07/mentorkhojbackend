<?php

namespace Tests\Unit;

use App\Model\BusinessSetting;
use App\Services\RazorpaySeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RazorpaySeminarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('razor_pay_credentials');
    }

    public function test_credentials_from_addon_settings_payment_config(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            $this->markTestSkipped('addon_settings table not available');
        }

        DB::table('addon_settings')->insert([
            'key_name' => 'razor_pay',
            'settings_type' => 'payment_config',
            'mode' => 'live',
            'is_active' => 1,
            'live_values' => json_encode([
                'status' => 1,
                'api_key' => 'rzp_live_test_key',
                'api_secret' => 'secret_live_test',
            ]),
            'test_values' => json_encode([
                'status' => 1,
                'api_key' => 'rzp_test_key',
                'api_secret' => 'secret_test',
            ]),
            'additional_data' => json_encode(['gateway_title' => 'Razorpay']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $creds = app(RazorpaySeminarService::class)->credentials();

        $this->assertSame('rzp_live_test_key', $creds['key_id']);
        $this->assertSame('secret_live_test', $creds['key_secret']);
    }

    public function test_credentials_from_business_settings_razor_key(): void
    {
        BusinessSetting::create([
            'key' => 'razor_pay',
            'value' => json_encode([
                'status' => 1,
                'razor_key' => 'rzp_legacy_key',
                'razor_secret' => 'legacy_secret',
            ]),
        ]);

        $creds = app(RazorpaySeminarService::class)->credentials();

        $this->assertSame('rzp_legacy_key', $creds['key_id']);
        $this->assertSame('legacy_secret', $creds['key_secret']);
    }

    public function test_verify_signature_uses_configured_secret(): void
    {
        BusinessSetting::create([
            'key' => 'razor_pay',
            'value' => json_encode([
                'status' => 1,
                'razor_key' => 'rzp_test',
                'razor_secret' => 'test_secret',
            ]),
        ]);

        $service = app(RazorpaySeminarService::class);
        $orderId = 'order_abc';
        $paymentId = 'pay_xyz';
        $signature = hash_hmac('sha256', $orderId . '|' . $paymentId, 'test_secret');

        $this->assertTrue($service->verifySignature($orderId, $paymentId, $signature));
        $this->assertFalse($service->verifySignature($orderId, $paymentId, 'bad_sig'));
    }
}
