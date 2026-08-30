<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\DemoBookingClaimLogic;
use App\Model\DemoBooking;
use App\Model\SessionChatMessage;
use App\Services\DemoBookingMailService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin report hub for free demo form fills.
 * Separate sections: All | NEET | JEE | Tech | AI
 */
class DemoBookingAdminController extends Controller
{
    public const VERTICALS = [
        'neet' => 'NEET',
        'jee' => 'JEE',
        'tech' => 'Tech',
        'ai' => 'AI/ML',
    ];

    public function index(Request $request)
    {
        $vertical = strtolower((string) $request->query('vertical', ''));
        $query = DemoBooking::query()->with('assignedMentors')->orderByDesc('created_at');

        if ($vertical && isset(self::VERTICALS[$vertical])) {
            $this->applyVerticalFilter($query, $vertical);
        } elseif ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('booking_ref', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv(clone $query, $vertical ?: 'all');
        }

        $counts = $this->verticalCounts();

        return view('admin-views.demo-bookings.index', [
            'bookings' => $query->paginate(50)->appends($request->query()),
            'filterCategory' => $request->query('category'),
            'filterVertical' => $vertical,
            'filterStatus' => $request->query('status'),
            'q' => $request->query('q'),
            'statuses' => DemoBooking::statuses(),
            'verticals' => self::VERTICALS,
            'counts' => $counts,
            'total_all' => array_sum($counts),
        ]);
    }

    public function show(int $id)
    {
        $booking = DemoBooking::with('assignedMentors')->findOrFail($id);
        $assignedIds = $booking->assignedMentors->pluck('id')->all();
        $publishedMentors = DemoBookingClaimLogic::eligiblePublishedMentors($booking);
        $verticalKey = $this->detectVertical($booking);

        $mentorIds = $booking->assignedMentors->pluck('id');
        $sessionChatMessages = SessionChatMessage::query()
            ->with(['mentee', 'mentor'])
            ->where(function ($q) use ($booking, $mentorIds) {
                $q->where('demo_booking_id', $booking->id);
                if ($booking->user_id && $mentorIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($booking, $mentorIds) {
                        $inner->where('mentee_user_id', $booking->user_id)
                            ->whereIn('mentor_id', $mentorIds);
                    });
                }
            })
            ->orderBy('id')
            ->limit(300)
            ->get();

        return view('admin-views.demo-bookings.show', [
            'booking' => $booking,
            'statuses' => DemoBooking::statuses(),
            'verticalKey' => $verticalKey,
            'publishedMentors' => $publishedMentors,
            'assignedIds' => $assignedIds,
            'mentorFilterLabel' => self::VERTICALS[$verticalKey] ?? ($booking->category_label ?: $booking->category ?: 'this category'),
            'sessionChatMessages' => $sessionChatMessages,
        ]);
    }

    public function storeMentors(Request $request, int $id, DemoBookingMailService $mail)
    {
        $booking = DemoBooking::findOrFail($id);
        $data = $request->validate([
            'mentor_ids' => 'required|array|min:1',
            'mentor_ids.*' => 'integer|exists:mentors,id',
        ]);

        DemoBookingClaimLogic::ensureInviteToken($booking);

        $existing = $booking->assignedMentors()->pluck('mentors.id')->all();
        $existing = array_map('intval', $existing);
        $toAttach = [];
        foreach (array_unique($data['mentor_ids']) as $mentorId) {
            $mentorId = (int) $mentorId;
            if (in_array($mentorId, $existing, true)) {
                continue;
            }
            if (!DemoBookingClaimLogic::mentorIsEligible($booking, $mentorId)) {
                continue;
            }
            $toAttach[$mentorId] = [
                'assigned_at' => now(),
                'paid_session_done' => false,
            ];
        }

        if ($toAttach) {
            $booking->assignedMentors()->attach($toAttach);
            $mailStatus = $mail->sendMentorAssignedEmail($booking);
            if ($mailStatus === 'success') {
                foreach (array_keys($toAttach) as $mentorId) {
                    $booking->assignedMentors()->updateExistingPivot($mentorId, [
                        'assignment_email_sent_at' => now(),
                    ]);
                }
            }
            $message = count($toAttach) . ' mentor(s) assigned.';
            if ($mailStatus === 'success') {
                $message .= ' Student email sent (Mentorkhoj copied).';
            } elseif ($mailStatus === 'no_email') {
                $message .= ' No student email on this lead — email not sent.';
            } else {
                $message .= ' Email failed — check logs.';
            }
        } else {
            $message = 'No new in-category mentors were assigned. Pick mentors that match this demo category.';
        }

        return redirect()
            ->route('admin.demo-bookings.show', $booking->id)
            ->with('success', $message);
    }

    public function destroyMentor(int $id, int $mentorId)
    {
        $booking = DemoBooking::findOrFail($id);
        $booking->assignedMentors()->detach($mentorId);

        return redirect()
            ->route('admin.demo-bookings.show', $booking->id)
            ->with('success', 'Mentor removed from this demo.');
    }

    public function updatePaid(Request $request, int $id)
    {
        $booking = DemoBooking::findOrFail($id);
        $paid = $request->input('paid_done', []);
        if (!is_array($paid)) {
            $paid = [];
        }

        foreach ($booking->assignedMentors()->pluck('mentors.id') as $mentorId) {
            $done = !empty($paid[$mentorId]);
            $booking->assignedMentors()->updateExistingPivot((int) $mentorId, [
                'paid_session_done' => $done,
            ]);
        }

        return redirect()
            ->route('admin.demo-bookings.show', $booking->id)
            ->with('success', 'Paid session status saved.');
    }

    public function update(Request $request, int $id)
    {
        $booking = DemoBooking::findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', DemoBooking::statuses()),
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $notes = trim((string) ($data['admin_notes'] ?? ''));
        $data['admin_notes'] = $notes !== '' ? $notes : null;

        if (($data['admin_notes'] ?? null) !== ($booking->admin_notes ?? null)) {
            $data['last_communication_at'] = $data['admin_notes'] ? now() : null;
        }

        $booking->update($data);

        return redirect()
            ->route('admin.demo-bookings.show', $booking->id)
            ->with('success', 'Demo booking updated.');
    }

    public function destroy(int $id)
    {
        $booking = DemoBooking::findOrFail($id);
        $ref = $booking->booking_ref;
        $booking->delete();

        return redirect()
            ->route('admin.demo-bookings.index')
            ->with('success', "Demo booking {$ref} removed.");
    }

    /** Apply NEET / JEE / Tech / AI filter on category + vertical columns. */
    protected function applyVerticalFilter($query, string $vertical): void
    {
        $map = [
            'neet' => ['neet'],
            'jee' => ['jee', 'iit-jee', 'iit'],
            'tech' => ['tech', 'sde'],
            'ai' => ['ai', 'ml', 'ai-ml'],
        ];
        $keys = $map[$vertical] ?? [$vertical];

        $query->where(function ($q) use ($keys) {
            foreach ($keys as $k) {
                $q->orWhere(function ($inner) use ($k) {
                    $inner->where('category', 'like', "%{$k}%")
                        ->orWhere('vertical', 'like', "%{$k}%");
                });
            }
        });
    }

    protected function detectVertical(DemoBooking $b): string
    {
        $raw = strtolower(($b->vertical ?? '') . ' ' . ($b->category ?? ''));
        if (str_contains($raw, 'neet')) {
            return 'neet';
        }
        if (str_contains($raw, 'jee') || str_contains($raw, 'iit')) {
            return 'jee';
        }
        if (str_contains($raw, 'tech') || str_contains($raw, 'sde')) {
            return 'tech';
        }
        if (str_contains($raw, 'ai') || str_contains($raw, 'ml')) {
            return 'ai';
        }

        return 'other';
    }

    /** @return array<string,int> */
    protected function verticalCounts(): array
    {
        $counts = ['neet' => 0, 'jee' => 0, 'tech' => 0, 'ai' => 0, 'other' => 0];
        DemoBooking::query()->select(['id', 'category', 'vertical'])->chunkById(200, function ($rows) use (&$counts) {
            foreach ($rows as $row) {
                $v = $this->detectVertical($row);
                if (!isset($counts[$v])) {
                    $counts[$v] = 0;
                }
                $counts[$v]++;
            }
        });

        return $counts;
    }

    protected function exportCsv($query, string $label): StreamedResponse
    {
        $filename = 'demo-leads-' . $label . '-' . date('Y-m-d') . '.csv';

        return Response::streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'booking_ref', 'created_at', 'name', 'phone', 'email',
                'category', 'category_label', 'vertical', 'stage', 'subjects',
                'status', 'source', 'utm_source', 'utm_medium', 'utm_campaign',
                'admin_notes', 'last_communication_at',
            ]);
            $query->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $b) {
                    fputcsv($out, [
                        $b->id,
                        $b->booking_ref,
                        $b->created_at,
                        $b->name,
                        $b->phone,
                        $b->email,
                        $b->category,
                        $b->category_label,
                        $b->vertical,
                        $b->stage,
                        is_array($b->subjects) ? implode('; ', $b->subjects) : $b->subjects,
                        $b->status,
                        $b->source,
                        $b->utm_source,
                        $b->utm_medium,
                        $b->utm_campaign,
                        $b->admin_notes,
                        $b->last_communication_at,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
