<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\WhatsAppCloudApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta WhatsApp webhook verification (GET) and event delivery (POST).
     */
    public function handle(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            $mode = $request->query('hub_mode', $request->query('hub.mode'));
            $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
            $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
            $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'mentorkhoj_whatsapp_verify');

            if ($mode === 'subscribe' && hash_equals((string) $verifyToken, (string) $token)) {
                return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        $secret = (string) env('META_APP_SECRET', '');
        if ($secret !== '') {
            $header = (string) $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, $header)) {
                return response('Forbidden', 403);
            }
        }

        $payload = $request->json()->all();
        if (is_array($payload) && ($payload['object'] ?? '') !== '') {
            WhatsAppCloudApi::ingestWebhook($payload);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
