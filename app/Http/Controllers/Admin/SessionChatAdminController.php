<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\SessionChatLogic;
use App\Http\Controllers\Controller;
use App\Model\SessionChatMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SessionChatAdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        $query = SessionChatMessage::query()
            ->with(['mentee', 'mentor'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', '%'.$search.'%')
                    ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', '%'.$search.'%'))
                    ->orWhereHas('mentee', function ($u) use ($search) {
                        $u->where('f_name', 'like', '%'.$search.'%');
                    });
            });
        }

        $messages = $query->paginate(Helpers::getPagination())->appends($request->query());

        $rows = $messages->getCollection()->map(function (SessionChatMessage $row) {
            return [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'sender_role' => $row->sender_role,
                'student_first_name' => SessionChatLogic::studentFirstName($row->mentee),
                'mentor_name' => $row->mentor?->display_name ?? '—',
                'body' => $row->body,
                'mentor_booking_id' => $row->mentor_booking_id,
            ];
        });
        $messages->setCollection($rows);

        return view('admin-views.session-chats.index', compact('messages', 'search'));
    }
}
