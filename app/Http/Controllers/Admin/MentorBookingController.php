<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorBookingLogic;
use App\Http\Controllers\Controller;
use App\Model\Mentor\MentorBooking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MentorBookingController extends Controller
{
    public function list(Request $request): View
    {
        $search = $request->get('search');
        $query = MentorBooking::with(['mentor', 'service', 'mentee'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('mentor', fn ($m) => $m->where('display_name', 'like', "%{$search}%"))
                    ->orWhereHas('mentee', function ($u) use ($search) {
                        $u->where('f_name', 'like', "%{$search}%")
                            ->orWhere('l_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $bookings = $query->paginate(Helpers::getPagination())->appends($request->query());

        return view('admin-views.mentor.bookings.list', compact('bookings', 'search'));
    }

    public function show(int $id): View
    {
        $booking = MentorBooking::with(['mentor', 'service', 'mentee', 'earnings'])->findOrFail($id);

        return view('admin-views.mentor.bookings.view', compact('booking'));
    }
}
