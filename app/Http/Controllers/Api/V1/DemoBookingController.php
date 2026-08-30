<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\DemoBookingClaimLogic;
use App\CentralLogics\MentorImageService;
use App\CentralLogics\WhatsAppDemoBookingModule;
use App\Model\DemoBooking;
use App\Model\Mentor\MentorBooking;
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

        DemoBookingClaimLogic::claimForUser($user, $request->query('demo_token') ?: $request->input('demo_token'));

        $email = trim((string) ($user->email ?? ''));
        $rows = DemoBooking::query()
            ->with('assignedMentors')
            ->where(function ($q) use ($user, $email) {
                $q->where('user_id', $user->id);
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $bookingsByMentor = MentorBooking::query()
            ->with('service')
            ->where('mentee_user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->groupBy('mentor_id');

        $payload = $rows->map(function (DemoBooking $booking) use ($bookingsByMentor) {
            $mentors = $booking->assignedMentors->map(function ($mentor) use ($bookingsByMentor) {
                $paidBookings = ($bookingsByMentor->get($mentor->id) ?? collect())->map(function (MentorBooking $b) {
                    return [
                        'id' => $b->id,
                        'title' => optional($b->service)->title,
                        'amount' => $b->amount,
                        'payment_status' => $b->payment_status,
                        'status' => $b->status,
                        'preferred_date' => optional($b->preferred_date)->toDateString(),
                    ];
                })->values();

                return [
                    'id' => $mentor->id,
                    'display_name' => $mentor->display_name,
                    'username' => $mentor->username,
                    'headline' => $mentor->headline,
                    'photo_url' => MentorImageService::apiPhotoUrl($mentor),
                    'profile_url' => $mentor->username
                        ? ('https://www.mentorkhoj.com/mentor/' . $mentor->username)
                        : null,
                    'paid_session_done' => (bool) $mentor->pivot->paid_session_done,
                    'paid_bookings' => $paidBookings,
                ];
            })->values();

            return [
                'id' => $booking->id,
                'booking_ref' => $booking->booking_ref,
                'category' => $booking->category,
                'category_label' => $booking->category_label ?: $booking->demoProgramLabel(),
                'status' => $booking->status,
                'assigned_mentors' => $mentors,
            ];
        })->values();

        return response()->json(['ok' => true, 'bookings' => $payload]);
    }
}
