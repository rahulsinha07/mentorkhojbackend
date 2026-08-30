<?php

namespace App\CentralLogics;

use App\Model\DemoBooking;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Order;
use App\Model\OrderDetail;
use App\User;
use Illuminate\Support\Str;

class InvoicePrefillLogic
{
    /** @return array<string, mixed> */
    public static function fromOrder(int $orderId): array
    {
        $order = Order::with(['details', 'customer'])->findOrFail($orderId);
        $customer = $order->customer;
        $items = [];

        /** @var OrderDetail $detail */
        foreach ($order->details as $index => $detail) {
            $productDetails = json_decode($detail->product_details ?? '{}', true) ?: [];
            $items[] = [
                'sort_order' => $index,
                'service_name' => $productDetails['name'] ?? ('Product #' . $detail->product_id),
                'description' => $productDetails['description'] ?? null,
                'sku' => (string) ($detail->product_id ?? ''),
                'quantity' => (float) $detail->quantity,
                'unit' => 'Qty',
                'unit_price' => (float) $detail->price,
                'discount' => (float) ($detail->discount_on_product ?? 0),
                'discount_type' => 'fixed',
                'tax_rate' => $detail->price > 0 ? round(((float) $detail->tax_amount / max(0.01, (float) $detail->price)) * 100, 2) : 0,
                'tax_type' => 'gst',
            ];
        }

        if (empty($items)) {
            $items[] = [
                'sort_order' => 0,
                'service_name' => 'Order #' . $order->id,
                'quantity' => 1,
                'unit' => 'Qty',
                'unit_price' => (float) $order->order_amount,
                'discount' => 0,
                'discount_type' => 'fixed',
                'tax_rate' => 18,
                'tax_type' => 'gst',
            ];
        }

        return static::buildPayload([
            'source_type' => 'order',
            'source_id' => $order->id,
            'user_id' => $order->user_id,
            'customer_name' => $customer ? trim($customer->f_name . ' ' . $customer->l_name) : 'Customer',
            'customer_email' => $customer->email ?? null,
            'customer_phone' => $customer->phone ?? null,
            'reference_number' => (string) $order->id,
            'payment_status' => static::mapPaymentStatus($order->payment_status),
            'payment_method' => $order->payment_method,
            'transaction_id' => $order->transaction_reference,
            'amount_paid' => in_array($order->payment_status, ['paid', 'partially_paid'], true) ? (float) $order->order_amount : 0,
            'items' => $items,
        ], $customer);
    }

    /** @return array<string, mixed> */
    public static function fromBooking(int $bookingId): array
    {
        $booking = MentorBooking::with(['mentee', 'service', 'mentor.user'])->findOrFail($bookingId);
        $mentee = $booking->mentee;
        $service = $booking->service;

        if ($booking->legacy_order_id) {
            try {
                $payload = static::fromOrder((int) $booking->legacy_order_id);
                $payload['source_type'] = 'mentor_booking';
                $payload['source_id'] = $booking->id;
                $payload['reference_number'] = 'MB-' . $booking->id;

                return $payload;
            } catch (\Throwable $e) {
                // fall through to booking-only prefill
            }
        }

        $amount = (float) $booking->amount;
        $taxRate = $amount > 0 ? round(((float) $booking->tax_amount / max(0.01, $amount)) * 100, 2) : 18;

        return static::buildPayload([
            'source_type' => 'mentor_booking',
            'source_id' => $booking->id,
            'user_id' => $booking->mentee_user_id,
            'customer_name' => $mentee ? trim(($mentee->f_name ?? '') . ' ' . ($mentee->l_name ?? '')) : 'Customer',
            'customer_email' => $mentee->email ?? null,
            'customer_phone' => $mentee->phone ?? null,
            'reference_number' => 'MB-' . $booking->id,
            'payment_status' => static::mapPaymentStatus($booking->payment_status),
            'payment_method' => $booking->payment_status === 'paid' ? 'online_payment' : null,
            'amount_paid' => $booking->payment_status === 'paid' ? $amount + (float) $booking->tax_amount : 0,
            'items' => [[
                'sort_order' => 0,
                'service_name' => $service->title ?? 'Mentorship Session',
                'description' => $booking->mentor && $booking->mentor->user
                    ? 'Mentor: ' . trim(($booking->mentor->user->f_name ?? '') . ' ' . ($booking->mentor->user->l_name ?? ''))
                    : null,
                'quantity' => 1,
                'unit' => 'Session',
                'unit_price' => $amount,
                'discount' => 0,
                'discount_type' => 'fixed',
                'tax_rate' => $taxRate,
                'tax_type' => 'gst',
            ]],
        ], $mentee);
    }

    /**
     * Resolve a demo booking by numeric ID or booking ref (e.g. DM-PDOQJR-7117).
     */
    public static function resolveDemoBooking(string $refOrId): DemoBooking
    {
        $refOrId = trim($refOrId);
        if ($refOrId === '') {
            throw new \InvalidArgumentException('Demo booking reference is required.');
        }

        $query = DemoBooking::query()->with(['user', 'assignedMentors.user']);

        if (ctype_digit($refOrId)) {
            return $query->where('id', (int) $refOrId)->firstOrFail();
        }

        $normalized = strtoupper($refOrId);

        $exact = (clone $query)->where(function ($q) use ($refOrId, $normalized) {
            $q->where('booking_ref', $refOrId)
                ->orWhere('booking_ref', $normalized);
        })->first();

        if ($exact) {
            return $exact;
        }

        $prefixMatches = (clone $query)->where(function ($q) use ($refOrId, $normalized) {
            $q->where('booking_ref', 'like', $refOrId . '%')
                ->orWhere('booking_ref', 'like', $normalized . '%');
        })->get();

        if ($prefixMatches->count() === 1) {
            return $prefixMatches->first();
        }

        if ($prefixMatches->count() > 1) {
            throw new \InvalidArgumentException(
                'Multiple demo bookings match that reference prefix. Please enter the full ref (e.g. DM-PDOQJR-7117).'
            );
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
            'Demo booking not found for reference: ' . $refOrId
        );
    }

    /** @return array<string, mixed> */
    public static function fromDemoBooking(string $refOrId): array
    {
        $demo = static::resolveDemoBooking($refOrId);
        $user = $demo->user;
        $settings = \App\Model\Invoice\InvoiceSetting::instance();
        $defaultTaxRate = (float) ($settings->default_tax_rate ?? 18);

        $subjects = is_array($demo->subjects) ? implode(', ', $demo->subjects) : '';
        $program = $demo->demoProgramLabel();
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        $customerName = trim((string) $demo->name);
        if ($customerName === '' && $user) {
            $customerName = trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? ''));
        }

        $mentors = $demo->assignedMentors;
        $mentorSnapshot = [];
        $items = [];

        /** @var Mentor $mentor */
        foreach ($mentors as $index => $mentor) {
            $mentorName = $mentor->display_name ?: ('Mentor #' . $mentor->id);
            $profileUrl = $mentor->username
                ? $siteUrl . '/mentor/' . $mentor->username
                : null;

            $descParts = array_filter([
                'Demo ref: ' . $demo->booking_ref,
                'Program: ' . $program,
                $demo->stage ? 'Stage: ' . $demo->stage : null,
                $subjects !== '' ? 'Subjects: ' . $subjects : null,
                $profileUrl ? 'Profile: ' . $profileUrl : null,
            ]);

            $items[] = [
                'sort_order' => $index,
                'mentor_id' => $mentor->id,
                'service_name' => $mentorName,
                'description' => implode(' | ', $descParts),
                'sku' => (string) $mentor->id,
                'quantity' => 1,
                'unit' => 'Session',
                'unit_price' => 0,
                'discount' => 0,
                'discount_type' => 'fixed',
                'tax_rate' => $defaultTaxRate,
                'tax_type' => 'gst',
            ];

            $mentorSnapshot[] = [
                'id' => $mentor->id,
                'name' => $mentorName,
                'username' => $mentor->username,
                'profile_url' => $profileUrl,
            ];
        }

        if (empty($items)) {
            $items[] = [
                'sort_order' => 0,
                'service_name' => 'Mentorship — ' . $program,
                'description' => 'Demo ref: ' . $demo->booking_ref . ' | Assign a mentor on the demo booking before invoicing.',
                'sku' => (string) $demo->booking_ref,
                'quantity' => 1,
                'unit' => 'Session',
                'unit_price' => 0,
                'discount' => 0,
                'discount_type' => 'fixed',
                'tax_rate' => $defaultTaxRate,
                'tax_type' => 'gst',
            ];
        }

        $payload = [
            'source_type' => 'demo_booking',
            'source_id' => $demo->id,
            'user_id' => $demo->user_id,
            'customer_name' => $customerName !== '' ? $customerName : 'Student',
            'customer_type' => 'Student',
            'customer_email' => $demo->email ?: ($user->email ?? null),
            'customer_phone' => $demo->phone ?: ($user->phone ?? null),
            'reference_number' => (string) $demo->booking_ref,
            'customer_external_id' => (string) $demo->booking_ref,
            'payment_status' => 'pending',
            'payment_method' => null,
            'amount_paid' => 0,
            'classes_booked' => 1,
            'customer_aadhaar' => null,
            'customer_notes' => 'Demo session — ' . $program . ' (' . $demo->booking_ref . ').',
            'items' => $items,
            'mentor_snapshot' => $mentorSnapshot,
            'mentors' => $mentorSnapshot,
            'demo_booking' => [
                'id' => $demo->id,
                'booking_ref' => $demo->booking_ref,
                'name' => $demo->name,
                'phone' => $demo->phone,
                'email' => $demo->email,
                'category' => $demo->category,
                'category_label' => $demo->category_label,
                'program' => $program,
                'stage' => $demo->stage,
                'subjects' => $demo->subjects,
                'status' => $demo->status,
                'source' => $demo->source,
                'vertical' => $demo->vertical,
                'message' => $demo->message,
                'created_at' => optional($demo->created_at)->toDateTimeString(),
            ],
        ];

        if ($user) {
            $address = $user->addresses()->first();
            if ($address) {
                $payload['billing_address'] = trim(implode(', ', array_filter([
                    $address->address,
                    $address->road ?? null,
                    $address->house ?? null,
                ])));
            }
        }

        $built = static::buildPayload($payload, $user);
        if ($user) {
            $built['customer_external_id'] = (string) $demo->booking_ref;
        }

        return $built;
    }

    /** @return array<string, mixed> */
    public static function fromUser(int $userId): array
    {
        $user = User::findOrFail($userId);

        return static::buildPayload([
            'user_id' => $user->id,
            'customer_name' => trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'items' => [],
        ], $user);
    }

    /** @return array<string, mixed> */
    private static function buildPayload(array $data, ?User $user): array
    {
        $settings = \App\Model\Invoice\InvoiceSetting::instance();
        $defaultTaxRate = (float) ($settings->default_tax_rate ?? 18);
        $billingState = $data['billing_state'] ?? null;

        $defaults = [
            'tax_mode' => $settings->default_tax_mode ?? InvoiceCalculationLogic::suggestTaxMode($billingState),
            'currency' => $settings->default_currency,
            'place_of_supply' => InvoiceCompanyProfile::get('default_place_of_supply'),
            'customer_notes' => $settings->default_notes,
            'terms' => $settings->default_terms,
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+' . (int) $settings->default_payment_terms_days . ' days')),
        ];

        if (!array_key_exists('customer_external_id', $data)) {
            $data['customer_external_id'] = $user ? (string) $user->id : null;
        }

        return array_merge($defaults, $data);
    }

    private static function mapPaymentStatus(?string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'partially_paid' => 'partially_paid',
            'failed' => 'cancelled',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }
}
