<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\WhatsAppDemoBookingModule;
use App\Model\DemoBooking;
use App\Services\DemoBookingMailService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class DemoBookingController extends Controller
{
    public function __construct(private DemoBookingMailService $mail) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:32',
            'email' => 'nullable|email|max:255',
            'category' => 'required|string|max:64',
            'category_label' => 'nullable|string|max:120',
            'stage' => 'required|string|max:120',
            'subjects' => 'nullable|array',
            'source' => 'nullable|string|max:32',
            'vertical' => 'nullable|string|max:64',
            'utm_source' => 'nullable|string|max:120',
            'utm_medium' => 'nullable|string|max:120',
            'utm_campaign' => 'nullable|string|max:120',
            'utm_content' => 'nullable|string|max:120',
            'message' => 'nullable|string|max:2000',
        ]);

        $ref = 'DM-' . strtoupper(Str::random(6)) . '-' . random_int(1000, 9999);

        $booking = DemoBooking::create([
            ...$data,
            'booking_ref' => $ref,
            'status' => 'new',
            'source' => $data['source'] ?? 'lp',
            'user_id' => optional($request->user())->id,
        ]);

        $mailResult = $this->mail->sendConfirmationEmails($booking);

        $notifications = [
            'email_student' => $mailResult['email_student'],
            'email_admin' => $mailResult['email_admin'],
            'whatsapp' => 'skipped',
            'whatsapp_template' => null,
        ];

        try {
            $wa = WhatsAppDemoBookingModule::sendDemoBooked(
                (string) $booking->phone,
                (string) $booking->name,
                (string) $booking->booking_ref,
                $booking->vertical ?? null,
                $booking->category ?? null,
                $booking->email ?? null,
                $booking->category_label ?? null,
            );
            $notifications['whatsapp'] = $wa['status'] ?? 'error';
            $notifications['whatsapp_template'] = $wa['template'] ?? null;
            if (!empty($wa['message'])) {
                $notifications['whatsapp_detail'] = $wa['message'];
            }
        } catch (\Throwable $e) {
            $notifications['whatsapp'] = 'error';
            report($e);
        }

        $label = $booking->category_label ?: $booking->category;

        return response()->json([
            'ok' => true,
            'booking_ref' => $booking->booking_ref,
            'id' => $booking->id,
            'message' => 'Demo booked — our team will contact you shortly.',
            'category_label' => $label,
            'email_sent' => in_array($mailResult['email_student'], ['success', 'already_sent'], true)
                || in_array($mailResult['email_admin'], ['success', 'already_sent'], true),
            'notifications' => $notifications,
        ], 201);
    }

    public function my(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $rows = DemoBooking::where('user_id', $user->id)
            ->orWhere('email', $user->email ?? '')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['ok' => true, 'bookings' => $rows]);
    }
}
