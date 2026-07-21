<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarBooking;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SeminarBookingAdminController extends Controller
{
    public function index(Request $request, int $id): View|Factory|Application
    {
        $seminar = Seminar::findOrFail($id);
        $filter = $request->query('payment_status');

        $query = SeminarBooking::where('seminar_id', $seminar->id)->orderByDesc('created_at');

        if ($filter === 'paid') {
            $query->where('payment_status', 'paid');
        } elseif ($filter === 'pending') {
            $query->where('payment_status', 'pending');
        } elseif ($filter === 'failed') {
            $query->where('payment_status', 'failed');
        } elseif ($filter === 'free') {
            $query->where('payment_status', 'not_required');
        }

        return view('admin-views.seminar.bookings', [
            'seminar' => $seminar,
            'bookings' => $query->paginate(50),
            'filter' => $filter,
        ]);
    }
}
