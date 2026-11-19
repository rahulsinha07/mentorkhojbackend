<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\WhatsAppDemoBookingModule;
use App\Model\DemoBooking;
use App\Services\DemoBookingMailService;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class GoogleAdsLeadWebhookController extends Controller
{
    use DetectsLostConnections;

    /**
     * Maps a Google Ads campaign ID to the demo-bookings vertical/category it belongs to.
     * https://developers.google.com/google-ads/webhook/docs/implementation
     */
    private const CAMPAIGN_VERTICALS = [
        '24435340013' => ['vertical' => 'jee', 'category' => 'jee', 'category_label' => 'JEE'],
        '24441165970' => ['vertical' => 'neet', 'category' => 'neet', 'category_label' => 'NEET'],
    ];

    /** Retries for transient DB connection drops (common on shared hosting). */
    private const DB_RETRY_TIMES = 3;
    private const DB_RETRY_DELAY_MS = 200;

    public function __construct(private DemoBookingMailService $mail) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $googleKey = (string) ($payload['google_key'] ?? '');
        $expectedKey = (string) env('GOOGLE_ADS_LEAD_WEBHOOK_KEY', '');
        if ($expectedKey === '' || !hash_equals($expectedKey, $googleKey)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!empty($payload['is_test'])) {
            return response()->json([], 200);
        }

        $leadId = (string) ($payload['lead_id'] ?? '');
        $alreadyExists = $leadId !== '' && $this->retryOnTransientDbError(
            fn () => DemoBooking::where('external_lead_id', $leadId)->exists()
        );
        if ($alreadyExists) {
            return response()->json([], 200);
        }

        $fields = [];
        foreach ((array) ($payload['user_column_data'] ?? []) as $column) {
            $columnId = (string) ($column['column_id'] ?? '');
            $value = trim((string) ($column['column_value']['string_value'] ?? ''));
            if ($columnId === '' || $value === '') {
                continue;
            }

            switch ($columnId) {
                case 'FULL_NAME':
                    $fields['name'] = $value;
                    break;
                case 'PHONE_NUMBER':
                    $fields['phone'] = $value;
                    break;
                case 'EMAIL':
                    $fields['email'] = $value;
                    break;
                default:
                    // The one custom question we configured ("What stage are you in?").
                    $fields['stage'] = $value;
                    break;
            }
        }

        if (empty($fields['name']) || empty($fields['phone'])) {
            return response()->json(['message' => 'Missing required lead fields'], 422);
        }

        $campaignId = (string) ($payload['campaign_id'] ?? '');
        $mapping = self::CAMPAIGN_VERTICALS[$campaignId] ?? [
            'vertical' => null,
            'category' => 'google_lead_form',
            'category_label' => null,
        ];

        $ref = 'DM-' . strtoupper(Str::random(6)) . '-' . random_int(1000, 9999);

        try {
            $booking = $this->retryOnTransientDbError(fn () => DemoBooking::create([
                'booking_ref' => $ref,
                'external_lead_id' => $leadId !== '' ? $leadId : null,
                'name' => $fields['name'],
                'phone' => $fields['phone'],
                'email' => $fields['email'] ?? null,
                'category' => $mapping['category'],
                'category_label' => $mapping['category_label'],
                'stage' => $fields['stage'] ?? 'Not specified',
                'source' => 'google_lead_form',
                'vertical' => $mapping['vertical'],
                'status' => 'new',
            ]));
        } catch (QueryException $e) {
            // A unique-key clash on external_lead_id means a concurrent/retried
            // delivery of this same lead already created the booking — treat as done.
            if ($leadId !== '' && $this->isDuplicateKeyError($e)) {
                return response()->json([], 200);
            }
            throw $e;
        }

        $mailResult = $this->mail->sendConfirmationEmails($booking);

        try {
            WhatsAppDemoBookingModule::sendDemoBooked(
                (string) $booking->phone,
                (string) $booking->name,
                (string) $booking->booking_ref,
                $booking->vertical ?? null,
                $booking->category ?? null,
                $booking->email ?? null,
                $booking->category_label ?? null,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        if ($mailResult['email_admin'] === 'error') {
            report(new \RuntimeException('Google Ads lead admin alert email failed for booking ' . $booking->id));
        }

        return response()->json([], 200);
    }

    /**
     * Retries $callback on a transient/lost DB connection (e.g. a shared-host
     * connection drop), using Laravel's own lost-connection detection so we
     * never retry a genuine query error (bad SQL, constraint violation, etc).
     */
    private function retryOnTransientDbError(\Closure $callback)
    {
        return retry(
            self::DB_RETRY_TIMES,
            $callback,
            self::DB_RETRY_DELAY_MS,
            fn (\Throwable $e) => $e instanceof QueryException && $this->isTransientConnectionError($e)
        );
    }

    /**
     * Laravel's own causedByLostConnection() only matches a fixed list of
     * "SQLSTATE[HY000] [2002] ..." suffixes (connection refused, DNS failure,
     * etc). This host has also been observed throwing "SQLSTATE[HY000] [2002]
     * Operation not permitted" under connection bursts, which isn't in that
     * list — so we also retry on any [2002] (client-side connect failure).
     */
    private function isTransientConnectionError(QueryException $e): bool
    {
        return $this->causedByLostConnection($e) || str_contains($e->getMessage(), 'SQLSTATE[HY000] [2002]');
    }

    private function isDuplicateKeyError(QueryException $e): bool
    {
        return (int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
