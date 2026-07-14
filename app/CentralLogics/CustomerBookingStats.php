<?php

namespace App\CentralLogics;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CustomerBookingStats
{
    public static function applyListAggregates(Builder $query): Builder
    {
        $cancelled = 'cancelled';

        return $query
            ->select('users.*')
            ->selectSub(function ($sub) use ($cancelled) {
                $sub->from('mentor_bookings as mb')
                    ->selectRaw('COUNT(*)')
                    ->where('mb.status', '!=', $cancelled)
                    ->where(function ($inner) {
                        $inner->whereColumn('mb.mentee_user_id', 'users.id')
                            ->orWhereIn('mb.mentor_id', function ($mentorIds) {
                                $mentorIds->select('id')
                                    ->from('mentors')
                                    ->whereColumn('user_id', 'users.id');
                            });
                    });
            }, 'bookings_count')
            ->selectSub(function ($sub) use ($cancelled) {
                $sub->from('mentor_bookings as mb')
                    ->selectRaw('COALESCE(SUM(mb.amount), 0)')
                    ->where('mb.status', '!=', $cancelled)
                    ->where(function ($inner) {
                        $inner->whereColumn('mb.mentee_user_id', 'users.id')
                            ->orWhereIn('mb.mentor_id', function ($mentorIds) {
                                $mentorIds->select('id')
                                    ->from('mentors')
                                    ->whereColumn('user_id', 'users.id');
                            });
                    });
            }, 'bookings_amount');
    }

    /**
     * @return array{count: int, amount: float}
     */
    public static function forUser(int $userId): array
    {
        $cancelled = 'cancelled';

        $row = DB::table('mentor_bookings as mb')
            ->where('mb.status', '!=', $cancelled)
            ->where(function ($query) use ($userId) {
                $query->where('mb.mentee_user_id', $userId)
                    ->orWhereIn('mb.mentor_id', function ($sub) use ($userId) {
                        $sub->select('id')->from('mentors')->where('user_id', $userId);
                    });
            })
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
