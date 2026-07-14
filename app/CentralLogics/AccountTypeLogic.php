<?php

namespace App\CentralLogics;

use App\User;
use Illuminate\Http\JsonResponse;

class AccountTypeLogic
{
    public static function normalizeLoginAs(mixed $value): ?string
    {
        if ($value === 'mentor' || $value === 'mentee') {
            return $value;
        }

        return null;
    }

    public static function accountTypeForRegistration(mixed $loginAs): string
    {
        return self::normalizeLoginAs($loginAs) === 'mentor' ? 'mentor' : 'mentee';
    }

    public static function validateLoginPortal(User $user, mixed $loginAs): ?JsonResponse
    {
        $portal = self::normalizeLoginAs($loginAs);
        if ($portal === null) {
            return null;
        }

        $accountType = $user->account_type ?? 'mentee';

        if ($portal === 'mentor' && $accountType === 'mentee') {
            return response()->json([
                'errors' => [[
                    'code' => 'wrong_login_portal',
                    'message' => 'Please use Student login for this account.',
                ]],
            ], 403);
        }

        if ($portal === 'mentee' && $accountType === 'mentor') {
            return response()->json([
                'errors' => [[
                    'code' => 'wrong_login_portal',
                    'message' => 'Please use Mentor login for this account.',
                ]],
            ], 403);
        }

        return null;
    }

    public static function recordLoginPortal(User $user, mixed $loginAs): void
    {
        $portal = self::normalizeLoginAs($loginAs) ?? ($user->account_type ?? 'mentee');
        $user->last_login_as = $portal;
        $user->last_login_at = now();
    }

    public static function loginMediumLabel(?string $medium): string
    {
        return match ($medium) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'apple' => 'Apple',
            default => 'Email / Phone',
        };
    }

    public static function loginPortalLabel(?string $portal): string
    {
        return match ($portal) {
            'mentor' => 'Mentor login',
            'mentee' => 'Student login',
            default => '—',
        };
    }

    public static function accountTypeLabel(?string $type): string
    {
        return match ($type) {
            'mentor' => 'Mentor',
            'mentee' => 'Student',
            default => '—',
        };
    }
}
