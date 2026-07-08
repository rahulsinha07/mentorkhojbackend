<?php

namespace App\Http\Controllers\Api\V1;

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
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');
            $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'mentorkhoj_whatsapp_verify');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200);
            }

            return response('Forbidden', 403);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
