<?php

namespace Tests\Feature;

use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarBooking;
use App\Services\RazorpaySeminarService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SeminarBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('oauth_clients')) {
            $this->artisan('passport:install', ['--force' => true]);
        }
    }

    private function createSeminar(array $overrides = []): Seminar
    {
        return Seminar::create(array_merge([
            'slug' => 'test-seminar-' . uniqid(),
            'title' => 'Test Seminar',
            'status' => 'active',
            'is_published' => true,
            'fee_amount' => 0,
            'currency' => 'INR',
            'sort_order' => 0,
        ], $overrides));
    }

    private function createUser(): User
    {
        return User::create([
            'f_name' => 'Test',
            'l_name' => 'User',
            'email' => 'test-' . uniqid() . '@example.com',
            'phone' => '9876543210',
            'password' => bcrypt('password'),
        ]);
    }

    private function bookingPayload(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'booker@example.com',
            'phone' => '9876543210',
            'org' => 'Test Org',
        ];
    }

    public function test_free_seminar_book_confirms_immediately(): void
    {
        $seminar = $this->createSeminar(['slug' => 'free-seminar', 'fee_amount' => 0]);
        $user = $this->createUser();
        Passport::actingAs($user);

        $response = $this->postJson("/api/v1/seminars/{$seminar->slug}/book", $this->bookingPayload());

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('payment_status', 'not_required')
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('is_free', true);

        $this->assertDatabaseHas('seminar_bookings', [
            'seminar_id' => $seminar->id,
            'email' => 'booker@example.com',
            'payment_status' => 'not_required',
            'status' => 'confirmed',
        ]);
    }

    public function test_paid_seminar_book_returns_requires_payment(): void
    {
        $seminar = $this->createSeminar(['slug' => 'paid-seminar', 'fee_amount' => 199]);
        $user = $this->createUser();
        Passport::actingAs($user);

        $response = $this->postJson("/api/v1/seminars/{$seminar->slug}/book", $this->bookingPayload());

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('requires_payment', true)
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('seminar_bookings', [
            'seminar_id' => $seminar->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_payment_order_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/seminar-bookings/1/payment-order');

        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_verify_payment_marks_confirmed(): void
    {
        $seminar = $this->createSeminar(['slug' => 'paid-verify', 'fee_amount' => 100]);
        $user = $this->createUser();

        $booking = SeminarBooking::create([
            'booking_ref' => 'SKB-TEST-0001',
            'seminar_id' => $seminar->id,
            'customer_id' => $user->id,
            'name' => 'Test User',
            'email' => 'paid@example.com',
            'phone' => '9876543210',
            'org' => 'Org',
            'amount' => 100,
            'currency' => 'INR',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->mock(RazorpaySeminarService::class, function ($mock) use ($booking) {
            $mock->shouldReceive('markPaid')
                ->once()
                ->andReturnUsing(function () use ($booking) {
                    $booking->update([
                        'payment_status' => 'paid',
                        'status' => 'confirmed',
                        'paid_at' => now(),
                    ]);

                    return $booking->fresh(['seminar']);
                });
        });

        Passport::actingAs($user);

        $response = $this->postJson("/api/v1/seminar-bookings/{$booking->id}/verify-payment", [
            'razorpay_order_id' => 'order_test',
            'razorpay_payment_id' => 'pay_test',
            'razorpay_signature' => 'sig_test',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('seminar_bookings', [
            'id' => $booking->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
    }

    public function test_deploy_health_logic_includes_seminar_checks(): void
    {
        $checks = \App\CentralLogics\DeployHealthLogic::checks();

        $this->assertArrayHasKey('seminar_bookings_table', $checks);
        $this->assertArrayHasKey('seminars_fee_amount_column', $checks);
        $this->assertTrue($checks['seminar_bookings_table']);
    }
}
