<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\AccountTypeLogic;
use App\CentralLogics\CustomerBookingStats;
use App\CentralLogics\CustomerProfileSync;
use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingMailLogic;
use App\CentralLogics\MentorSessionLifecycleLogic;
use App\CentralLogics\SessionChatLogic;
use App\CentralLogics\SessionCreditLogic;
use App\Http\Controllers\Controller;
use App\Model\Conversation;
use App\Model\DemoBooking;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Mentor\MentorSessionCredit;
use App\Model\SessionChatMessage;
use App\Model\SessionChatMessagingOverride;
use App\Model\Newsletter;
use App\Model\Order;
use App\User;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        private User $user,
        private Order $order,
        private Newsletter $newsletter,
        private Conversation $conversation
    ){}

    /**
     * @param Request $request
     * @return Factory|View|Application
     */
    public function list(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        $type = $request->query('type', 'student') === 'mentor' ? 'mentor' : 'student';
        $queryParam['type'] = $type;

        $baseQuery = CustomerBookingStats::applyListAggregates(
            $this->user->with(['mentorProfile']),
            $type
        );
        $this->applyAccountTypeFilter($baseQuery, $type);

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $customers = $baseQuery->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam['search'] = $request['search'];
        } else {
            $customers = $baseQuery;
        }
        $customers = $customers->latest()->paginate(Helpers::getPagination())->appends($queryParam);

        $tabCounts = [
            'student' => $this->user->newQuery()
                ->where(function ($q) {
                    $q->where('account_type', 'mentee')->orWhereNull('account_type');
                })
                ->count(),
            'mentor' => $this->user->newQuery()->where('account_type', 'mentor')->count(),
        ];

        return view('admin-views.customer.list', compact('customers', 'search', 'type', 'tabCounts'));
    }

    /**
     * Filter customers to mentor or student (mentee) tab.
     */
    protected function applyAccountTypeFilter($query, string $type): void
    {
        if ($type === 'mentor') {
            $query->where('account_type', 'mentor');
            return;
        }

        $query->where(function ($q) {
            $q->where('account_type', 'mentee')->orWhereNull('account_type');
        });
    }

    /**
     * @param Request $request
     * @param $id
     * @return View|Factory|RedirectResponse|Application
     */
    public function view(Request $request, $id): Factory|View|Application|RedirectResponse
    {
        $customer = $this->user->find($id);
        if (isset($customer)) {
            CustomerProfileSync::syncFromDemoBookings($customer);
            $customer->refresh();

            $queryParam = [];
            $search = $request['search'];
            if($request->has('search'))
            {
                $key = explode(' ', $request['search']);
                $orders = $this->order->where(['user_id' => $id])
                    ->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('id', 'like', "%{$value}%")
                                ->orWhere('order_amount', 'like', "%{$value}%");
                        }
                });
                $queryParam = ['search' => $request['search']];
            }else{
                $orders = $this->order->where(['user_id' => $id]);
            }
            $orders = $orders->latest()->paginate(Helpers::getPagination())->appends($queryParam);
            $customer->load('mentorProfile');
            $bookingStats = CustomerBookingStats::forUser((int) $id);

            $mentorBookings = MentorBooking::with(['mentor', 'service'])
                ->where('mentee_user_id', $id)
                ->latest()
                ->paginate(Helpers::getPagination(), ['*'], 'sessions_page');

            $demoBookings = DemoBooking::query()
                ->where(function ($q) use ($id, $customer) {
                    $q->where('user_id', $id);
                    if ($customer->email) {
                        $q->orWhere('email', $customer->email);
                    }
                })
                ->orderByDesc('created_at')
                ->get();

            $sessionChatMessages = SessionChatMessage::query()
                ->with(['mentee', 'mentor'])
                ->where('mentee_user_id', $id)
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            $sessionCredits = MentorSessionCredit::with(['mentor.services' => function ($q) {
                $q->where('is_enabled', true)->orderBy('sort_order');
            }])
                ->where('mentee_user_id', $id)
                ->orderByDesc('updated_at')
                ->get();
            $creditsRemainingTotal = $sessionCredits->sum(fn (MentorSessionCredit $c) => $c->remaining());

            $messagingPairs = $this->messagingPairsForCustomer((int) $id, $sessionCredits, $demoBookings);

            $activeMentors = Mentor::query()
                ->where('status', 'active')
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'username']);

            return view('admin-views.customer.customer-view', compact(
                'customer',
                'orders',
                'search',
                'bookingStats',
                'mentorBookings',
                'demoBookings',
                'sessionChatMessages',
                'sessionCredits',
                'creditsRemainingTotal',
                'messagingPairs',
                'activeMentors'
            ));
        }
        Toastr::error(translate('Customer not found!'));
        return back();
    }

    /**
     * Mentor↔mentee messaging rows for admin toggle (packs + bookings + demos).
     *
     * @param  \Illuminate\Support\Collection<int, MentorSessionCredit>  $sessionCredits
     * @param  \Illuminate\Support\Collection<int, DemoBooking>  $demoBookings
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function messagingPairsForCustomer(int $menteeUserId, $sessionCredits, $demoBookings)
    {
        $mentorIds = collect();

        foreach ($sessionCredits as $credit) {
            $mentorIds->push((int) $credit->mentor_id);
        }

        $bookingMentorIds = MentorBooking::query()
            ->where('mentee_user_id', $menteeUserId)
            ->distinct()
            ->pluck('mentor_id')
            ->map(fn ($id) => (int) $id);
        $mentorIds = $mentorIds->merge($bookingMentorIds);

        $demoIds = $demoBookings->pluck('id');
        if ($demoIds->isNotEmpty()) {
            $demoMentorIds = \Illuminate\Support\Facades\DB::table('demo_booking_mentors')
                ->whereIn('demo_booking_id', $demoIds)
                ->pluck('mentor_id')
                ->map(fn ($id) => (int) $id);
            $mentorIds = $mentorIds->merge($demoMentorIds);
        }

        $mentorIds = $mentorIds->filter(fn ($id) => $id > 0)->unique()->values();
        $mentors = Mentor::query()->whereIn('id', $mentorIds)->get()->keyBy('id');
        $creditsByMentor = $sessionCredits->keyBy('mentor_id');

        return $mentorIds->map(function (int $mentorId) use ($menteeUserId, $mentors, $creditsByMentor) {
            $entitlement = SessionChatLogic::messagingEntitlement($menteeUserId, $mentorId);
            $credit = $creditsByMentor->get($mentorId);

            return [
                'mentor_id' => $mentorId,
                'mentor_name' => $mentors->get($mentorId)?->display_name ?? ('#'.$mentorId),
                'has_pack' => $credit !== null,
                'credits_total' => $credit?->credits_total ?? 0,
                'credits_used' => $credit?->credits_used ?? 0,
                'credits_remaining' => $credit ? $credit->remaining() : 0,
                'available_to_schedule' => $credit ? $credit->availableToSchedule() : 0,
                'fund_total' => $credit?->fund_total ?? 0,
                'fund_used' => $credit?->fund_used ?? 0,
                'fund_remaining' => $credit ? $credit->fundRemaining() : 0,
                'per_session_net' => $credit ? $credit->currentSessionNetRate() : 0,
                'credit' => $credit,
                'entitlement' => $entitlement,
                'messaging_active' => (bool) ($entitlement['active'] ?? false),
                'messaging_label' => SessionChatLogic::entitlementLabel($entitlement),
                'override' => $entitlement['override'] ?? null,
            ];
        })->values();
    }

    public function toggleSessionMessaging(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'mentor_id' => 'required|integer|exists:mentors,id',
            'enabled' => 'required|in:0,1',
        ]);

        $customer = $this->user->findOrFail($id);
        $mentorId = (int) $validated['mentor_id'];
        $enabled = (int) $validated['enabled'] === 1;

        if (SessionChatLogic::isSupportMentor($mentorId)) {
            Toastr::error(translate('Support chat cannot be toggled'));
            return back();
        }

        SessionChatMessagingOverride::query()->updateOrCreate(
            [
                'mentee_user_id' => (int) $customer->id,
                'mentor_id' => $mentorId,
            ],
            [
                'override' => $enabled
                    ? SessionChatMessagingOverride::FORCE_ON
                    : SessionChatMessagingOverride::FORCE_OFF,
            ]
        );

        Toastr::success($enabled
            ? translate('Messaging enabled for this mentor–student pair')
            : translate('Messaging disabled for this mentor–student pair'));

        return back();
    }

    public function storeSessionCredits(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'mentor_id' => 'required|integer|exists:mentors,id',
            'credits' => 'required|integer|min:1|max:500',
            'total_amount' => 'required|numeric|min:0.01|max:9999999',
            'notes' => 'nullable|string|max:2000',
            'schedule_now' => 'nullable|boolean',
            'start_date' => 'required_if:schedule_now,1|nullable|date',
            'start_time' => 'required_if:schedule_now,1|nullable|string|max:32',
            'mentor_service_id' => 'nullable|integer',
        ]);

        $customer = $this->user->findOrFail($id);
        $mentor = Mentor::findOrFail($validated['mentor_id']);

        try {
            $credit = SessionCreditLogic::grant(
                $customer,
                $mentor,
                (int) $validated['credits'],
                (float) $validated['total_amount'],
                auth('admin')->id(),
                $validated['notes'] ?? null
            );

            if (!empty($validated['schedule_now'])) {
                MentorSessionLifecycleLogic::ensurePackSessionRows($credit);
                $mode = (string) ($request->input('mode') ?: 'one_off');
                $count = $mode === 'one_off' ? 1 : (int) $validated['credits'];
                SessionCreditLogic::scheduleSessions($credit->fresh(), [
                    'mode' => $mode,
                    'start_date' => $validated['start_date'],
                    'start_time' => $validated['start_time'],
                    'count' => $count,
                    'mentor_service_id' => $validated['mentor_service_id'] ?? null,
                    'mentee_note' => $validated['notes'] ?? null,
                ]);
            }

            Toastr::success(translate('Sessions added'));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function scheduleFromCredits(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'credit_id' => 'required|integer|exists:mentor_session_credits,id',
            'mode' => 'required|in:one_off,daily,weekly',
            'start_date' => 'required|date',
            'start_time' => 'required|string|max:32',
            'count' => 'nullable|integer|min:1|max:52',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'mentor_service_id' => 'nullable|integer',
            'mentee_note' => 'nullable|string|max:2000',
        ]);

        $credit = MentorSessionCredit::where('id', $validated['credit_id'])
            ->where('mentee_user_id', $id)
            ->firstOrFail();

        try {
            MentorSessionLifecycleLogic::ensurePackSessionRows($credit);
            $bookings = SessionCreditLogic::scheduleSessions($credit, $validated);
            Toastr::success(translate('Scheduled').' '.$bookings->count().' '.translate('session(s)'));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function completeBooking(int $id): RedirectResponse
    {
        $booking = MentorBooking::findOrFail($id);

        try {
            SessionCreditLogic::markComplete($booking);
            Toastr::success(translate('Session marked complete'));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
        }

        return back();
    }

    public function rescheduleBooking(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string|max:32',
        ]);

        $booking = MentorBooking::with(['mentor.user', 'service', 'mentee'])->findOrFail($id);
        if (!SessionCreditLogic::canReschedule($booking)) {
            Toastr::error(translate('Only upcoming sessions can be rescheduled'));
            return back();
        }

        $timeRaw = (string) $validated['preferred_time'];
        $time = strlen($timeRaw) === 5 ? $timeRaw.':00' : $timeRaw;
        try {
            $when = \Carbon\Carbon::parse($validated['preferred_date'].' '.$time);
        } catch (\Throwable $e) {
            Toastr::error(translate('Invalid date or time'));
            return back();
        }
        if ($when->lt(now()->subMinute())) {
            Toastr::error(translate('Choose a date and time from now onward'));
            return back();
        }

        $alreadyNotified = (bool) $booking->schedule_notify_sent_at;
        $booking->preferred_date = $validated['preferred_date'];
        $booking->preferred_time = $time;
        if ($booking->status === 'requested') {
            $booking->status = 'confirmed';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('mentor_bookings', 'session_reminder_24h_sent_at')) {
            $booking->session_reminder_24h_sent_at = null;
        }
        $booking->save();
        MentorBookingMailLogic::sendScheduleConfirmedNotify($booking, true);

        Toastr::success(translate('Session rescheduled'));
        return back();
    }

    public function sendPaymentReminder(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'payment_link' => 'nullable|url|max:2000',
        ]);

        $booking = MentorBooking::with(['mentor', 'service', 'mentee'])->findOrFail($id);

        if (!in_array($booking->payment_status, ['pending', 'failed'], true)) {
            Toastr::error(translate('Payment is not pending for this booking'));
            return back();
        }

        $sent = MentorBookingMailLogic::sendPaymentReminderEmail(
            $booking,
            $validated['payment_link'] ?? null
        );

        if ($sent) {
            Toastr::success(translate('Payment reminder email sent'));
        } else {
            Toastr::error(translate('Failed to send payment reminder email'));
        }

        return back();
    }

    /**
     * @param Request $request
     * @return Application|Factory|View
     */
    public function subscribedEmails(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $newsletters = $this->newsletter->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('email', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $request['search']];
        } else {
            $newsletters = $this->newsletter;
        }

        $newsletters = $newsletters->latest()->paginate(Helpers::getPagination())->appends($queryParam);
        return view('admin-views.customer.subscribed-list', compact('newsletters', 'search'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): RedirectResponse
    {
        $customer = $this->user->find($request->id);

        if (!$customer) {
            Toastr::error(translate('Customer not found!'));
            return back();
        }

        $runningOrdersCount = $this->order
            ->where(['user_id' => $request->id, 'is_guest' => 0])
            ->whereIn('order_status', ['confirmed', 'processing', 'out_for_delivery'])
            ->count();

        if ($runningOrdersCount > 0){
            Toastr::error(translate("This customer have {$runningOrdersCount} running order. Please complete the running order first"));
            return back();
        }

        if (Storage::disk('public')->exists('customer/' . $customer['image'])) {
            Storage::disk('public')->delete('customer/' . $customer['image']);
        }

        $conversations = $this->conversation->where('user_id', $request->id)->get();
        foreach ($conversations as $conversation){
            if ($conversation->checked == 0){
                $conversation->checked = 1;
                $conversation->save();
            }
        }

        $customerDeleted = $customer->delete();

        if ($customerDeleted) {
            $pendingOrders = $this->order
                ->where(['user_id' => $request->id, 'is_guest' => 0])
                ->where(['order_status' => 'pending'])
                ->get();

            if ($pendingOrders->isNotEmpty()) {
                foreach ($pendingOrders as $order) {
                    $order->order_status = 'canceled';
                    $order->save();
                }
            }
        }

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1 && isset($customer['email'])) {
                $name = $customer->f_name. ' '. $customer->l_name;
                Mail::to($customer->email)->send(new \App\Mail\Customer\CustomerDelete($name));
            }
        } catch (\Exception $e) {
        }

        Toastr::success(translate('Customer removed!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $user = $this->user->find($request->id);
        $user->is_block = $request->status;
        $user->save();

        try {
            $emailServices = Helpers::get_business_settings('mail_config');
            if (isset($emailServices['status']) && $emailServices['status'] == 1 && isset($user['email'])) {
                Mail::to($user->email)->send(new \App\Mail\Customer\CustomerChangeStatus($user));
            }
        } catch (\Exception $e) {
        }

        Toastr::success(translate('Block status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());
            return back();
        }

        $customer = $this->user->find($id);
        if (!$customer) {
            Toastr::error(translate('Customer not found!'));
            return back();
        }

        $customer->password = bcrypt($request->password);
        $customer->save();

        if ($request->boolean('notify_customer') && $customer->email) {
            try {
                $emailServices = Helpers::get_business_settings('mail_config');
                if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                    $name = trim(($customer->f_name ?? '') . ' ' . ($customer->l_name ?? ''));
                    Mail::to($customer->email)->send(new \App\Mail\Customer\AdminPasswordReset($name, $request->password));
                }
            } catch (\Exception $e) {
            }
        }

        Toastr::success(translate('Password updated successfully'));
        return back();
    }

    /**
     * @param Request $request
     * @return StreamedResponse|string
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportCustomer(Request $request): StreamedResponse|string
    {
        $storage = [];
        $search = $request['search'];
        $type = $request->query('type', 'student') === 'mentor' ? 'mentor' : 'student';

        $customers = CustomerBookingStats::applyListAggregates(
            $this->user->with('mentorProfile'),
            $type
        );
        $this->applyAccountTypeFilter($customers, $type);
        $customers = $customers->when($request->has('search'), function ($query) use ($request) {
                $key = explode(' ', $request['search']);
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    }
                });
            })
            ->get();

        foreach($customers as $customer){

            $storage[] = [
                'first_name' => $customer['f_name'],
                'last_name' => $customer['l_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'account_type' => AccountTypeLogic::accountTypeLabel($customer->account_type ?? null),
                'mentor_username' => $customer->mentorProfile?->username,
                'last_login_portal' => AccountTypeLogic::loginPortalLabel($customer->last_login_as ?? null),
                'last_login_at' => $customer->last_login_at?->format('Y-m-d H:i'),
                'login_method' => AccountTypeLogic::loginMediumLabel($customer->login_medium ?? null),
                'total_sessions' => (int) ($customer->bookings_count ?? 0),
                'total_session_amount' => (float) ($customer->bookings_amount ?? 0),
            ];
        }
        return (new FastExcel($storage))->download('customers-'.$type.'.xlsx');
    }
}
