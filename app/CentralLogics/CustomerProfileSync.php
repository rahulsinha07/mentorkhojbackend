<?php

namespace App\CentralLogics;

use App\Model\DemoBooking;
use App\User;

class CustomerProfileSync
{
    /**
     * Link demo bookings by email and sync phone from latest demo lead.
     */
    public static function syncFromDemoBookings(User $user): void
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return;
        }

        DemoBooking::query()
            ->where('email', $email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        if (trim((string) ($user->phone ?? '')) !== '') {
            return;
        }

        $latestPhone = DemoBooking::query()
            ->where(function ($q) use ($user, $email) {
                $q->where('user_id', $user->id)
                    ->orWhere('email', $email);
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByDesc('created_at')
            ->value('phone');

        if ($latestPhone) {
            $user->phone = $latestPhone;
            $user->save();
        }
    }
}
