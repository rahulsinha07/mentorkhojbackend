<?php

namespace App\Services;

use App\Mail\SeminarBookingConfirmed;
use App\Model\Seminar\SeminarBooking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Uses the same RazorPay credentials as mentor checkout (/payment-mobile).
 */
class RazorpaySeminarService
{
    /** @return array{key_id: string, key_secret: string} */
    public function credentials(): array
    {
        return Cache::remember('razor_pay_credentials', 300, function () {
            $raw = \App\Model\BusinessSetting::where('key', 'razor_pay')->value('value');
            $config = is_string($raw) ? json_decode($raw, true) : [];

            if (!is_array($config)) {
                $config = [];
            }

            return [
                'key_id' => (string) (
                    $config['razor_pay_api_key']
                    ?? $config['api_key']
                    ?? $config['key']
                    ?? ''
                ),
                'key_secret' => (string) (
                    $config['razor_pay_api_secret']
                    ?? $config['api_secret']
                    ?? $config['secret']
                    ?? ''
                ),
            ];
        });
    }

    public function keyId(): string
    {
        return $this->credentials()['key_id'];
    }

    public function createOrder(SeminarBooking $booking): array
    {
        $creds = $this->credentials();
        if (!$creds['key_id'] || !$creds['key_secret']) {
            throw new \RuntimeException(
                'RazorPay is not configured. Set API keys in Admin → Business Settings → RazorPay.',
            );
        }

        $amountPaise = (int) round((float) $booking->amount * 100);

        $response = Http::withBasicAuth($creds['key_id'], $creds['key_secret'])
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => $booking->currency ?? 'INR',
                'receipt' => $booking->booking_ref,
                'notes' => [
                    'booking_id' => (string) $booking->id,
                    'seminar_id' => (string) $booking->seminar_id,
                    'type' => 'seminar_booking',
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Razorpay order failed', ['body' => $response->json()]);
            throw new \RuntimeException('Could not create payment order.');
        }

        $data = $response->json();
        $booking->update(['razorpay_order_id' => $data['id']]);

        return $data;
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->credentials()['key_secret'];
        if (!$secret) {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    public function markPaid(
        SeminarBooking $booking,
        string $orderId,
        string $paymentId,
        string $signature,
    ): SeminarBooking {
        if (!$this->verifySignature($orderId, $paymentId, $signature)) {
            throw new \RuntimeException('Invalid payment signature.');
        }

        if ($booking->payment_status === 'paid') {
            return $booking;
        }

        $booking->update([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->sendConfirmationEmail($booking);

        return $booking->fresh(['seminar']);
    }

    public function markPaidFromTransactionReference(
        SeminarBooking $booking,
        string $transactionReference,
    ): SeminarBooking {
        if ($booking->payment_status === 'paid') {
            return $booking;
        }

        $creds = $this->credentials();
        if (!$creds['key_id'] || !$creds['key_secret']) {
            throw new \RuntimeException('RazorPay is not configured.');
        }

        $response = Http::withBasicAuth($creds['key_id'], $creds['key_secret'])
            ->get('https://api.razorpay.com/v1/payments/' . $transactionReference);

        if (!$response->successful()) {
            Log::error('Razorpay payment fetch failed', ['body' => $response->json()]);
            throw new \RuntimeException('Could not verify payment.');
        }

        $payment = $response->json();
        $status = (string) ($payment['status'] ?? '');
        if (!in_array($status, ['captured', 'authorized'], true)) {
            throw new \RuntimeException('Payment was not completed.');
        }

        $expectedPaise = (int) round((float) $booking->amount * 100);
        $paidPaise = (int) ($payment['amount'] ?? 0);
        if ($paidPaise > 0 && $paidPaise !== $expectedPaise) {
            throw new \RuntimeException('Payment amount does not match booking fee.');
        }

        $booking->update([
            'razorpay_order_id' => $payment['order_id'] ?? $booking->razorpay_order_id,
            'razorpay_payment_id' => $transactionReference,
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->sendConfirmationEmail($booking);

        return $booking->fresh(['seminar']);
    }

    public function markPaymentFailed(SeminarBooking $booking, ?string $reason = null): SeminarBooking
    {
        if ($booking->payment_status === 'paid') {
            return $booking;
        }

        $booking->update([
            'payment_status' => 'failed',
            'status' => 'pending',
        ]);

        if ($reason) {
            Log::info('Seminar booking payment failed', [
                'booking_id' => $booking->id,
                'reason' => $reason,
            ]);
        }

        return $booking->fresh(['seminar']);
    }

    public function prepareForPaymentRetry(SeminarBooking $booking): SeminarBooking
    {
        if ($booking->payment_status === 'paid') {
            throw new \RuntimeException('This booking is already paid.');
        }

        if (!in_array($booking->payment_status, ['pending', 'failed'], true)) {
            throw new \RuntimeException('Payment is not required for this booking.');
        }

        if ($booking->payment_status === 'failed') {
            $booking->update([
                'payment_status' => 'pending',
                'status' => 'pending',
            ]);
        }

        return $booking->fresh(['seminar']);
    }

    public function sendConfirmationEmail(SeminarBooking $booking): void
    {
        if ($booking->email_sent_at) {
            return;
        }

        try {
            Mail::to($booking->email)->send(new SeminarBookingConfirmed($booking));
            $booking->update(['email_sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Seminar booking email failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
