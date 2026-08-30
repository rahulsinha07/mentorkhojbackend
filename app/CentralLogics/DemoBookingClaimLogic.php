<?php

namespace App\CentralLogics;

use App\Model\DemoBooking;
use App\Model\Mentor\Mentor;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoBookingClaimLogic
{
    public static function ensureInviteToken(DemoBooking $booking): string
    {
        if (!$booking->profile_invite_token) {
            $booking->profile_invite_token = Str::random(40);
            $booking->save();
        }

        return (string) $booking->profile_invite_token;
    }

    public static function claimForUser(User $user, ?string $token = null): void
    {
        $token = trim((string) $token);
        if ($token !== '') {
            DemoBooking::where(function ($q) use ($token) {
                $q->where('profile_invite_token', $token)
                    ->orWhere('booking_ref', $token);
            })->update(['user_id' => $user->id]);
        }

        $email = trim((string) $user->email);
        if ($email !== '') {
            DemoBooking::where('email', $email)
                ->where(function ($q) {
                    $q->whereNull('user_id')->orWhere('user_id', 0);
                })
                ->update(['user_id' => $user->id]);
        }
    }

    public static function studentHasAccount(DemoBooking $booking): bool
    {
        if ($booking->user_id) {
            return true;
        }
        $email = trim((string) $booking->email);
        if ($email === '') {
            return false;
        }

        return User::where('email', $email)->exists();
    }

    public static function profileCtaUrl(DemoBooking $booking): string
    {
        $site = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
        $code = trim((string) $booking->booking_ref) ?: self::ensureInviteToken($booking);

        return $site . '/d/' . rawurlencode($code);
    }

    /** Category IDs on mentor profiles that match this demo vertical. */
    public static function categoryIdsForBooking(DemoBooking $booking): array
    {
        $raw = trim((string) $booking->category);
        if ($raw !== '' && ctype_digit($raw)) {
            return [(int) $raw];
        }

        $key = WhatsAppDemoBookingModule::verticalKey(
            $booking->vertical ? (string) $booking->vertical : null,
            $booking->category ? (string) $booking->category : null
        );

        $map = [
            'neet' => [26],
            'jee' => [59, 60, 61, 62, 63, 64, 65],
            'tech' => [6],
            'ai' => [49],
        ];

        return $map[$key] ?? [];
    }

    public static function eligiblePublishedMentors(DemoBooking $booking): Collection
    {
        $ids = self::categoryIdsForBooking($booking);
        $query = Mentor::query()->published()->orderBy('display_name');
        if (!$ids) {
            return $query->whereRaw('0=1')->get(['id', 'display_name', 'username', 'headline']);
        }

        $query->where(function ($inner) use ($ids) {
            foreach ($ids as $catId) {
                $inner->orWhere('category_ids', 'like', '%"id":' . $catId . '%')
                    ->orWhere('category_ids', 'like', '%"id":"' . $catId . '"%');
            }
        });

        return $query->get(['id', 'display_name', 'username', 'headline']);
    }

    public static function mentorIsEligible(DemoBooking $booking, int $mentorId): bool
    {
        return self::eligiblePublishedMentors($booking)->contains('id', $mentorId);
    }
}
