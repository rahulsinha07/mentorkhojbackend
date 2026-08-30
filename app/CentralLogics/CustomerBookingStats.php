<?php

namespace App\CentralLogics;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CustomerBookingStats
{
    public static function applyListAggregates(Builder $query, string $type = 'student'): Builder
    {
        $cancelled = 'cancelled';

        if ($type === 'mentor') {
            return $query
                ->select('users.*')
                ->selectSub(function ($sub) use ($cancelled) {
                    $sub->from('mentor_bookings as mb')
                        ->selectRaw('COUNT(*)')
                        ->where('mb.status', '!=', $cancelled)
                        ->whereIn('mb.mentor_id', function ($mentorIds) {
                            $mentorIds->select('id')
                                ->from('mentors')
                                ->whereColumn('user_id', 'users.id');
                        });
                }, 'bookings_count')
                ->selectSub(function ($sub) use ($cancelled) {
                    $sub->from('mentor_bookings as mb')
                        ->selectRaw('COALESCE(SUM(mb.amount), 0)')
                        ->where('mb.status', '!=', $cancelled)
                        ->whereIn('mb.mentor_id', function ($mentorIds) {
                            $mentorIds->select('id')
                                ->from('mentors')
                                ->whereColumn('user_id', 'users.id');
                        });
                }, 'bookings_amount');
        }

        return self::applyStudentListAggregates($query, $cancelled);
    }

    public static function applyStudentListAggregates(Builder $query, string $cancelled = 'cancelled'): Builder
    {
        return $query
            ->select('users.*')
            ->selectSub(function ($sub) use ($cancelled) {
                $sub->from('mentor_bookings as mb')
                    ->selectRaw('COUNT(*)')
                    ->where('mb.status', '!=', $cancelled)
                    ->whereColumn('mb.mentee_user_id', 'users.id');
            }, 'bookings_count')
            ->selectSub(function ($sub) use ($cancelled) {
                $sub->from('mentor_bookings as mb')
                    ->selectRaw('COALESCE(SUM(mb.amount), 0)')
                    ->where('mb.status', '!=', $cancelled)
                    ->whereColumn('mb.mentee_user_id', 'users.id');
            }, 'bookings_amount')
            ->selectSub(function ($sub) use ($cancelled) {
                $sub->from('mentor_bookings as mb')
                    ->selectRaw('COUNT(*)')
                    ->where('mb.status', '!=', $cancelled)
                    ->whereColumn('mb.mentee_user_id', 'users.id')
                    ->whereIn('mb.payment_status', ['pending', 'failed']);
            }, 'pending_payment_count')
            ->selectSub(function ($sub) {
                $sub->from('mentor_bookings as mb')
                    ->select('mb.id')
                    ->whereColumn('mb.mentee_user_id', 'users.id')
                    ->orderByDesc('mb.created_at')
                    ->limit(1);
            }, 'latest_mentee_booking_id')
            ->selectSub(function ($sub) {
                $sub->from('mentor_bookings as mb')
                    ->join('mentors as m', 'm.id', '=', 'mb.mentor_id')
                    ->select('m.display_name')
                    ->whereColumn('mb.mentee_user_id', 'users.id')
                    ->orderByDesc('mb.created_at')
                    ->limit(1);
            }, 'latest_mentor_name')
            ->selectSub(function ($sub) {
                $sub->from('mentor_bookings as mb')
                    ->select('mb.preferred_date')
                    ->whereColumn('mb.mentee_user_id', 'users.id')
                    ->orderByDesc('mb.created_at')
                    ->limit(1);
            }, 'latest_session_date')
            ->selectSub(function ($sub) {
                $sub->from('mentor_bookings as mb')
                    ->select('mb.preferred_time')
                    ->whereColumn('mb.mentee_user_id', 'users.id')
                    ->orderByDesc('mb.created_at')
                    ->limit(1);
            }, 'latest_session_time')
            ->selectSub(function ($sub) {
                $sub->from('mentor_bookings as mb')
                    ->select('mb.payment_status')
                    ->whereColumn('mb.mentee_user_id', 'users.id')
                    ->orderByDesc('mb.created_at')
                    ->limit(1);
            }, 'latest_payment_status')
            ->selectSub(function ($sub) {
                $sub->from('demo_bookings as db')
                    ->selectRaw('COUNT(*)')
                    ->where(function ($inner) {
                        $inner->whereColumn('db.user_id', 'users.id')
                            ->orWhereColumn('db.email', 'users.email');
                    });
            }, 'demo_bookings_count')
            ->selectSub(function ($sub) {
                $sub->from('demo_bookings as db')
                    ->select('db.booking_ref')
                    ->where(function ($inner) {
                        $inner->whereColumn('db.user_id', 'users.id')
                            ->orWhereColumn('db.email', 'users.email');
                    })
                    ->orderByDesc('db.created_at')
                    ->limit(1);
            }, 'latest_demo_ref')
            ->selectSub(function ($sub) {
                $sub->from('demo_bookings as db')
                    ->select('db.vertical')
                    ->where(function ($inner) {
                        $inner->whereColumn('db.user_id', 'users.id')
                            ->orWhereColumn('db.email', 'users.email');
                    })
                    ->orderByDesc('db.created_at')
                    ->limit(1);
            }, 'latest_demo_vertical');
    }

    /**
     * @return array{count: int, amount: float}
     */
    public static function forUser(int $userId): array
    {
        $cancelled = 'cancelled';

        $row = DB::table('mentor_bookings as mb')
            ->where('mb.status', '!=', $cancelled)
            ->where('mb.mentee_user_id', $userId)
            ->selectRaw('COUNT(*) as bookings_count, COALESCE(SUM(mb.amount), 0) as bookings_amount')
            ->first();

        return [
            'count' => (int) ($row->bookings_count ?? 0),
            'amount' => (float) ($row->bookings_amount ?? 0),
        ];
    }

    public static function legacyOrderStats(User $user): array
    {
        $orders = $user->relationLoaded('orders') ? $user->orders : $user->orders()->get();

        return [
            'count' => $orders->count(),
            'amount' => (float) $orders->sum('order_amount'),
        ];
    }
}
