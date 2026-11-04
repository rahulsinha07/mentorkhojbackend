<?php

namespace App\Http\Controllers\Api\V1\Mentor;

use App\CentralLogics\MentorDocumentLogic;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorDocument;
use App\Model\Mentor\MentorDocumentFile;
use App\Model\Mentor\MentorDocumentPurchase;
use App\Model\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MentorDocumentController extends Controller
{
    public static function formatPurchase(MentorDocumentPurchase $purchase): array
    {
        $purchase->loadMissing(['document.files', 'mentor']);

        return [
            'id' => $purchase->id,
            'mentor_id' => $purchase->mentor_id,
            'mentor_document_id' => $purchase->mentor_document_id,
            'legacy_order_id' => $purchase->legacy_order_id,
            'amount' => $purchase->amount,
            'tax_amount' => $purchase->tax_amount,
            'platform_fee' => $purchase->platform_fee,
            'mentor_net' => $purchase->mentor_net,
            'total_amount' => round((float) $purchase->amount + (float) $purchase->tax_amount, 2),
            'payment_status' => $purchase->payment_status,
            'requires_payment' => $purchase->payment_status === 'pending',
            'can_retry_payment' => $purchase->payment_status === 'failed',
            'document' => $purchase->document ? [
                'id' => $purchase->document->id,
                'title' => $purchase->document->title,
                'description' => $purchase->document->description,
                'files' => $purchase->document->files->map(fn ($f) => [
                    'id' => $f->id,
                    'original_name' => $f->original_name,
                    'mime' => $f->mime,
                    'size' => $f->size,
                    'download_url' => "/api/v1/mentors/my/document-purchases/{$purchase->id}/download/{$f->id}",
                ])->values(),
            ] : null,
            'mentor' => $purchase->mentor ? [
                'id' => $purchase->mentor->id,
                'username' => $purchase->mentor->username,
                'display_name' => $purchase->mentor->display_name,
            ] : null,
            'created_at' => $purchase->created_at?->toIso8601String(),
        ];
    }

    public function purchase(Request $request, int $mentorId, int $documentId): JsonResponse
    {
        $mentor = Mentor::published()->find($mentorId);
        if (!$mentor) {
            return response()->json(['errors' => [['message' => 'Mentor not found']]], 404);
        }

        $user = $request->user();
        if ($user) {
            $isMentorAccount = ($user->account_type ?? '') === 'mentor';
            $ownsMentorProfile = Mentor::where('user_id', $user->id)->exists();
            $buyingOwnProfile = (int) ($mentor->user_id ?? 0) === (int) $user->id;
            if ($isMentorAccount || $ownsMentorProfile || $buyingOwnProfile) {
                return response()->json([
                    'errors' => [[
                        'message' => 'This is a mentor account. Please create a new student account to purchase from there.',
                    ]],
                ], 403);
            }
        }

        $document = MentorDocument::where('mentor_id', $mentor->id)
            ->where('id', $documentId)
            ->where('is_enabled', true)
            ->first();

        if (!$document) {
            return response()->json(['errors' => [['message' => 'Document not available']]], 404);
        }

        $menteeId = $user?->id;
        if (!$menteeId) {
            return response()->json(['errors' => [['message' => 'Login required']]], 401);
        }

        $existing = MentorDocumentLogic::findRetryablePurchase($menteeId, $mentor->id, $document->id);
        if ($existing) {
            if ($existing->payment_status === 'failed') {
                $existing = MentorDocumentLogic::prepareForPaymentRetry($existing);
            }

            return response()->json([
                'message' => $existing->payment_status === 'pending'
                    ? 'Existing purchase resumed. Complete payment to confirm.'
                    : 'Purchase created',
                'purchase' => self::formatPurchase($existing->fresh()),
            ], 200);
        }

        $purchase = MentorDocumentLogic::createPurchase($mentor, $document, $menteeId);

        return response()->json([
            'message' => 'Purchase created',
            'purchase' => self::formatPurchase($purchase),
        ], 201);
    }

    public function checkoutContext(Request $request, int $id): JsonResponse
    {
        $purchase = MentorDocumentPurchase::with(['document', 'mentor'])->find($id);
        if (!$purchase || (int) $purchase->mentee_user_id !== (int) $request->user()->id) {
            return response()->json(['errors' => [['message' => 'Purchase not found']]], 404);
        }

        if ($purchase->payment_status === 'paid') {
            return response()->json([
                'errors' => [['message' => 'This purchase is already paid.']],
                'purchase' => self::formatPurchase($purchase),
            ], 400);
        }

        if ($purchase->payment_status === 'failed') {
            try {
                $purchase = MentorDocumentLogic::prepareForPaymentRetry($purchase);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['errors' => [['message' => $e->getMessage()]]], 400);
            }
        }

        if ($purchase->payment_status !== 'pending') {
            return response()->json(['errors' => [['message' => 'Payment is not available for this purchase.']]], 400);
        }

        $mentor = $purchase->mentor;
        $legacyProductId = $mentor?->legacy_product_id;
        if (!$legacyProductId) {
            return response()->json([
                'errors' => [[
                    'message' => 'Online payment is not available for this mentor. Legacy product link is missing.',
                ]],
                'purchase' => self::formatPurchase($purchase),
            ], 422);
        }
        $product = Product::find($legacyProductId);
        $branch = Branch::active()->first();

        return response()->json([
            'purchase' => self::formatPurchase($purchase),
            'legacy_product_id' => $legacyProductId,
            'variation_type' => $purchase->document
                ? preg_replace('/\s+/', '', $purchase->document->title)
                : null,
            'amount' => $purchase->amount,
            'tax_amount' => $purchase->tax_amount,
            'total' => round((float) $purchase->amount + (float) $purchase->tax_amount, 2),
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'tax' => $product->tax,
                'tax_type' => $product->tax_type,
            ] : null,
            'branch_id' => $branch?->id,
            'wallet_balance' => (float) ($request->user()->wallet_balance ?? 0),
        ]);
    }

    public function verifyPayment(Request $request, int $id): JsonResponse
    {
        $purchase = MentorDocumentPurchase::with(['document', 'mentor'])->find($id);
        if (!$purchase || (int) $purchase->mentee_user_id !== (int) $request->user()->id) {
            return response()->json(['errors' => [['message' => 'Purchase not found']]], 404);
        }

        if ($purchase->payment_status === 'paid') {
            return response()->json([
                'ok' => true,
                'message' => 'Payment confirmed! Your document is ready to download.',
                'purchase' => self::formatPurchase($purchase->fresh(['document', 'mentor'])),
            ]);
        }

        $validated = $request->validate([
            'razorpay_order_id' => 'required_without:transaction_reference|nullable|string',
            'razorpay_payment_id' => 'required_with:razorpay_order_id|nullable|string',
            'razorpay_signature' => 'required_with:razorpay_order_id|nullable|string',
            'payment_method' => 'required_with:transaction_reference|nullable|string',
            'transaction_reference' => 'required_without:razorpay_order_id|nullable|string',
        ]);

        try {
            $purchase = MentorDocumentLogic::verifyAndMarkPaid($purchase, $validated);
        } catch (\RuntimeException $e) {
            $purchase = MentorDocumentLogic::markPaymentFailed($purchase, $e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'purchase' => self::formatPurchase($purchase),
                'can_retry_payment' => true,
            ], 400);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Payment confirmed! Your document is ready to download.',
            'purchase' => self::formatPurchase($purchase->fresh(['document', 'mentor'])),
        ]);
    }

    public function myPurchases(Request $request): JsonResponse
    {
        $purchases = MentorDocumentPurchase::where('mentee_user_id', $request->user()->id)
            ->where('payment_status', 'paid')
            ->with(['document.files', 'mentor'])
            ->latest()
            ->get();

        return response()->json([
            'purchases' => $purchases->map(fn ($p) => self::formatPurchase($p))->values(),
        ]);
    }

    public function download(Request $request, int $id, int $fileId)
    {
        $purchase = MentorDocumentPurchase::find($id);
        if (!$purchase || (int) $purchase->mentee_user_id !== (int) $request->user()->id) {
            return response()->json(['errors' => [['message' => 'Purchase not found']]], 404);
        }

        if ($purchase->payment_status !== 'paid') {
            return response()->json(['errors' => [['message' => 'Payment required']]], 403);
        }

        $file = MentorDocumentFile::where('mentor_document_id', $purchase->mentor_document_id)
            ->where('id', $fileId)
            ->first();

        if (!$file) {
            return response()->json(['errors' => [['message' => 'File not found']]], 404);
        }

        if (!Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['errors' => [['message' => 'File not found']]], 404);
        }

        $name = $file->original_name ?: 'document';
        $mime = $file->mime ?: 'application/octet-stream';

        return Storage::disk('local')->download($file->file_path, $name, [
            'Content-Type' => $mime,
        ]);
    }
}
