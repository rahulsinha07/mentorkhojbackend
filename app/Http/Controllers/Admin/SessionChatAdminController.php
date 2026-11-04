<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\SessionChatLogic;
use App\CentralLogics\SessionChatMailLogic;
use App\CentralLogics\SessionChatPushLogic;
use App\Http\Controllers\Controller;
use App\Model\SessionChatMessage;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionChatAdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $supportMentorId = SessionChatLogic::ensureSupportMentorId();

        $query = SessionChatMessage::query()
            ->with(['mentee', 'mentor'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', '%'.$search.'%')
                    ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('mentee', function ($u) use ($search) {
                        $u->where('f_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        $messages = $query->paginate(Helpers::getPagination())->appends($request->query());

        $rows = $messages->getCollection()->map(function (SessionChatMessage $row) {
            $isAdmin = $row->sender_role === 'admin'
                || SessionChatLogic::isSupportMentor((int) $row->mentor_id);

            return [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'sender_role' => $row->sender_role,
                'student_first_name' => SessionChatLogic::studentFirstName($row->mentee),
                'mentee_user_id' => (int) $row->mentee_user_id,
                'mentor_name' => $isAdmin && $row->sender_role === 'admin'
                    ? SessionChatLogic::supportMentorDisplayName()
                    : ($row->mentor?->display_name ?? '—'),
                'body' => SessionChatLogic::emailBodyForMessage($row),
                'attachment_name' => $row->attachment_original_name,
                'mentor_booking_id' => $row->mentor_booking_id,
            ];
        });
        $messages->setCollection($rows);

        $studentQuery = trim((string) $request->get('student_q', ''));
        $students = collect();
        if ($studentQuery !== '') {
            $students = User::query()
                ->where(function ($q) use ($studentQuery) {
                    $q->where('email', 'like', '%'.$studentQuery.'%')
                        ->orWhere('phone', 'like', '%'.$studentQuery.'%')
                        ->orWhere('f_name', 'like', '%'.$studentQuery.'%')
                        ->orWhere('l_name', 'like', '%'.$studentQuery.'%');
                })
                ->orderByDesc('id')
                ->limit(25)
                ->get(['id', 'f_name', 'l_name', 'email', 'phone']);
        }

        $prefillMenteeId = (int) $request->get('mentee_user_id', 0);
        $prefillStudent = $prefillMenteeId > 0
            ? User::query()->find($prefillMenteeId, ['id', 'f_name', 'l_name', 'email', 'phone'])
            : null;

        return view('admin-views.session-chats.index', compact(
            'messages',
            'search',
            'students',
            'studentQuery',
            'supportMentorId',
            'prefillStudent'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'mentee_user_id' => 'required|integer|min:1',
            'body' => 'required|string|max:2000',
        ]);

        $menteeUserId = (int) $request->input('mentee_user_id');
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return back()->with('error', 'Message body is required.');
        }

        $mentee = User::query()->find($menteeUserId);
        if (!$mentee) {
            return back()->with('error', 'Student not found.');
        }

        $mentorId = SessionChatLogic::ensureSupportMentorId();
        $related = SessionChatLogic::relatedIds($menteeUserId, $mentorId);

        $row = SessionChatMessage::create([
            'mentee_user_id' => $menteeUserId,
            'mentor_id' => $mentorId,
            'demo_booking_id' => $related['demo_booking_id'],
            'mentor_booking_id' => $related['mentor_booking_id'],
            'sender_role' => 'admin',
            'body' => $body,
        ]);

        SessionChatMailLogic::notify($row);
        SessionChatPushLogic::notify($row);

        return redirect()
            ->route('admin.mentor.session-messages', ['mentee_user_id' => $menteeUserId])
            ->with('success', 'ADMIN message sent to '.SessionChatLogic::studentFirstName($mentee).'.');
    }
}
