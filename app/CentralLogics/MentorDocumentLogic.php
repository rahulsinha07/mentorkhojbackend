<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorDocument;
use App\Model\Mentor\MentorDocumentFile;
use App\Model\Mentor\MentorDocumentPurchase;
use App\Model\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MentorDocumentLogic
{
    /** Per-file size cap, in kilobytes (50MB). */
    public const FILE_MAX_KB = 51200;

    /** Per-bundle total size cap, in bytes (200MB). */
    public const BUNDLE_MAX_BYTES = 200 * 1024 * 1024;

    /**
     * Resolve charge amounts for a document bundle. Mirrors
     * MentorBookingLogic::resolvePayableAmounts via the shared helper.
     *
     * @return array{amount: float, tax_amount: float, platform_fee: float, mentor_net: float, total: float, is_free_session: bool}
     */
    public static function resolvePayableAmounts(Mentor $mentor, MentorDocument $document): array
    {
        return MentorBookingLogic::resolvePayableAmountsForPrice($mentor, (float) $document->price);
    }

    public static function createPurchase(
        Mentor $mentor,
        MentorDocument $document,
        int $menteeUserId
    ): MentorDocumentPurchase {
        $payable = self::resolvePayableAmounts($mentor, $document);
        $amount = $payable['amount'];
        $taxAmount = $payable['tax_amount'];
        $platformFee = $payable['platform_fee'];
        $mentorNet = $payable['mentor_net'];

        $purchase = MentorDocumentPurchase::create([
            'mentor_id' => $mentor->id,
            'mentor_document_id' => $document->id,
            'mentee_user_id' => $menteeUserId,
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'platform_fee' => $platformFee,
            'mentor_net' => $mentorNet,
            'payment_status' => ($amount + $taxAmount) <= 0 ? 'paid' : 'pending',
        ]);

        if ($purchase->payment_status === 'paid') {
            MentorEarningsLogic::creditDocumentPurchase($purchase);
        }

        return $purchase;
    }

    public static function findRetryablePurchase(
        int $menteeUserId,
        int $mentorId,
        int $documentId
    ): ?MentorDocumentPurchase {
        return MentorDocumentPurchase::where('mentee_user_id', $menteeUserId)
            ->where('mentor_id', $mentorId)
            ->where('mentor_document_id', $documentId)
            ->whereIn('payment_status', ['pending', 'failed'])
            ->latest('id')
            ->first();
    }

    public static function markPaymentFailed(MentorDocumentPurchase $purchase, ?string $reason = null): MentorDocumentPurchase
    {
        if ($purchase->payment_status === 'paid') {
            return $purchase;
        }

        $purchase->update([
            'payment_status' => 'failed',
        ]);

        if ($reason) {
            \Illuminate\Support\Facades\Log::info('Mentor document purchase payment failed', [
                'purchase_id' => $purchase->id,
                'reason' => $reason,
            ]);
        }

        return $purchase->fresh();
    }

    public static function prepareForPaymentRetry(MentorDocumentPurchase $purchase): MentorDocumentPurchase
    {
        if ($purchase->payment_status === 'paid') {
            throw new \InvalidArgumentException('This purchase is already paid.');
        }

        if (!in_array($purchase->payment_status, ['pending', 'failed'], true)) {
            throw new \InvalidArgumentException('Payment retry is not available for this purchase.');
        }

        $purchase->update([
            'payment_status' => 'pending',
            'legacy_order_id' => null,
        ]);

        return $purchase->fresh();
    }

    /**
     * Link a document purchase to a legacy order and sync payment status.
     */
    public static function syncFromLegacyOrder(MentorDocumentPurchase $purchase, Order $order): void
    {
        $purchase->legacy_order_id = $order->id;

        $orderPayment = (string) ($order->payment_status ?? 'unpaid');
        if (in_array($orderPayment, ['paid', 'partially_paid'], true)) {
            $purchase->payment_status = 'paid';
            if (!$purchase->earnings()->exists()) {
                MentorEarningsLogic::creditDocumentPurchase($purchase);
            }
        } elseif (in_array($orderPayment, ['unpaid', 'failed'], true)) {
            if ($purchase->payment_status !== 'paid') {
                $purchase->payment_status = $orderPayment === 'failed' ? 'failed' : 'pending';
            }
        }

        $purchase->save();
    }

    public static function markPaid(MentorDocumentPurchase $purchase, ?Order $order = null): MentorDocumentPurchase
    {
        if ($purchase->payment_status === 'paid') {
            return $purchase->fresh();
        }

        if ($order) {
            self::syncFromLegacyOrder($purchase, $order);

            return $purchase->fresh();
        }

        $purchase->update([
            'payment_status' => 'paid',
        ]);

        if (!$purchase->earnings()->exists()) {
            MentorEarningsLogic::creditDocumentPurchase($purchase);
        }

        return $purchase->fresh();
    }

    /**
     * Verify a Razorpay payment and mark the document purchase as paid.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public static function verifyAndMarkPaid(MentorDocumentPurchase $purchase, array $paymentData): MentorDocumentPurchase
    {
        if ($purchase->payment_status === 'paid') {
            return self::markPaid($purchase);
        }

        if (!empty($purchase->legacy_order_id)) {
            $order = Order::find($purchase->legacy_order_id);
            if ($order && in_array((string) $order->payment_status, ['paid', 'partially_paid'], true)) {
                return self::markPaid($purchase, $order);
            }
        }

        $transactionReference = trim((string) (
            $paymentData['transaction_reference']
            ?? $paymentData['razorpay_payment_id']
            ?? ''
        ));

        if ($transactionReference !== '') {
            $linkedOrder = Order::where('transaction_reference', $transactionReference)
                ->where('user_id', $purchase->mentee_user_id)
                ->latest('id')
                ->first();

            if ($linkedOrder && in_array((string) $linkedOrder->payment_status, ['paid', 'partially_paid'], true)) {
                return self::markPaid($purchase, $linkedOrder);
            }

            self::assertRazorpayPaymentMatchesPurchase($purchase, $transactionReference);

            if (!empty($purchase->legacy_order_id)) {
                Order::where('id', $purchase->legacy_order_id)->update([
                    'transaction_reference' => $transactionReference,
                    'payment_status' => 'paid',
                    'order_status' => 'confirmed',
                ]);
            }

            return self::markPaid($purchase);
        }

        if (!empty($paymentData['razorpay_order_id'])
            && !empty($paymentData['razorpay_payment_id'])
            && !empty($paymentData['razorpay_signature'])) {
            self::assertRazorpaySignature(
                (string) $paymentData['razorpay_order_id'],
                (string) $paymentData['razorpay_payment_id'],
                (string) $paymentData['razorpay_signature'],
            );

            if (!empty($purchase->legacy_order_id)) {
                Order::where('id', $purchase->legacy_order_id)->update([
                    'transaction_reference' => (string) $paymentData['razorpay_payment_id'],
                    'payment_status' => 'paid',
                    'order_status' => 'confirmed',
                ]);
            }

            return self::markPaid($purchase);
        }

        throw new \RuntimeException('Payment details are missing or invalid.');
    }

    /**
     * @param  int[]  $purchaseIds
     */
    public static function syncPurchasesFromLegacyOrder(array $purchaseIds, Order $order, ?int $userId = null): void
    {
        foreach ($purchaseIds as $purchaseId) {
            $purchase = MentorDocumentPurchase::find((int) $purchaseId);
            if (!$purchase) {
                continue;
            }
            if ($userId !== null && (int) $purchase->mentee_user_id !== (int) $userId) {
                continue;
            }
            self::syncFromLegacyOrder($purchase, $order);
        }
    }

    public static function hasAccess(int $menteeUserId, int $mentorDocumentId): bool
    {
        return MentorDocumentPurchase::where('mentee_user_id', $menteeUserId)
            ->where('mentor_document_id', $mentorDocumentId)
            ->where('payment_status', 'paid')
            ->exists();
    }

    public static function storeFile(MentorDocument $document, UploadedFile $file): MentorDocumentFile
    {
        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > self::FILE_MAX_KB) {
            throw new \InvalidArgumentException('This file exceeds the 50MB per-file limit.');
        }

        $existingTotal = (int) $document->total_size_bytes;
        if (($existingTotal + (int) $file->getSize()) > self::BUNDLE_MAX_BYTES) {
            throw new \InvalidArgumentException('Adding this file would exceed the 200MB total bundle limit.');
        }

        $path = $file->store("mentor-documents/{$document->mentor_id}/{$document->id}", 'local');
        if (!$path) {
            throw new \InvalidArgumentException('Could not store the uploaded file.');
        }

        $documentFile = MentorDocumentFile::create([
            'mentor_document_id' => $document->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'sort_order' => ($document->files()->max('sort_order') ?? 0) + 1,
        ]);

        $document->increment('total_size_bytes', (int) $file->getSize());

        return $documentFile;
    }

    public static function deleteFile(MentorDocumentFile $file): void
    {
        if (Storage::disk('local')->exists($file->file_path)) {
            Storage::disk('local')->delete($file->file_path);
        }

        $document = $file->document;
        if ($document) {
            $document->decrement('total_size_bytes', min((int) $file->size, (int) $document->total_size_bytes));
        }

        $file->delete();
    }

    private static function assertRazorpayPaymentMatchesPurchase(MentorDocumentPurchase $purchase, string $transactionReference): void
    {
        $creds = app(\App\Services\RazorpaySeminarService::class)->credentials();
        if (!$creds['key_id'] || !$creds['key_secret']) {
            throw new \RuntimeException('RazorPay is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withBasicAuth($creds['key_id'], $creds['key_secret'])
            ->get('https://api.razorpay.com/v1/payments/' . $transactionReference);

        if (!$response->successful()) {
            throw new \RuntimeException('Could not verify payment.');
        }

        $payment = $response->json();
        $status = (string) ($payment['status'] ?? '');
        if (!in_array($status, ['captured', 'authorized'], true)) {
            throw new \RuntimeException('Payment was not completed.');
        }

        $expectedPaise = (int) round(((float) $purchase->amount + (float) $purchase->tax_amount) * 100);
        $paidPaise = (int) ($payment['amount'] ?? 0);
        if ($paidPaise > 0 && $expectedPaise > 0 && $paidPaise !== $expectedPaise) {
            throw new \RuntimeException('Payment amount does not match purchase amount.');
        }
    }

    private static function assertRazorpaySignature(string $orderId, string $paymentId, string $signature): void
    {
        $secret = app(\App\Services\RazorpaySeminarService::class)->credentials()['key_secret'];
        if (!$secret) {
            throw new \RuntimeException('RazorPay is not configured.');
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid payment signature.');
        }
    }
}
