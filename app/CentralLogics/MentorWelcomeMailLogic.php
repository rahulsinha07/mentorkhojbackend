<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class MentorWelcomeMailLogic
{
    public static function sendWelcomeEmail(Mentor $mentor, User $user): bool
    {
        if (!FormMailLogic::isMailEnabled()) {
            Log::warning('Mentor welcome mail skipped: SMTP not configured');

            return false;
        }

        if (self::hasWelcomeEmailColumn() && $mentor->welcome_email_sent_at) {
            return true;
        }

        $email = self::resolveUserEmail($user);
        if (!$email) {
            Log::warning('Mentor welcome mail skipped: no email on user account', [
                'mentor_id' => $mentor->id,
                'user_id' => $user->id,
                'login_medium' => $user->login_medium,
            ]);

            return false;
        }

        try {
            Mail::to($email)
                ->cc(FormMailLogic::notifyEmail())
                ->send(new \App\Mail\Form\FormSubmissionMail(
                    'Welcome to MentorKhoj — your mentor profile is ready',
                    'email-templates.form.mentor-welcome',
                    FormMailLogic::withBrandPublic(self::welcomeContext($mentor, $user))
                ));

            if (self::hasWelcomeEmailColumn()) {
                $mentor->welcome_email_sent_at = now();
                $mentor->save();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Mentor welcome email failed: ' . $e->getMessage(), [
                'mentor_id' => $mentor->id,
                'user_id' => $user->id,
            ]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    public static function welcomeContext(Mentor $mentor, User $user): array
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return [
            'mentor_first_name' => self::firstName($mentor, $user),
            'display_name' => $mentor->display_name,
            'username' => $mentor->username,
            'headline' => $mentor->headline,
            'profile_url' => MentorLogic::profileUrl($mentor),
            'dashboard_url' => $siteUrl . '/mentor/dashboard',
            'created_at' => $mentor->created_at?->format('l, d M Y') ?? now()->format('l, d M Y'),
            'login_medium' => self::loginMediumLabel($user->login_medium),
            'support_email' => FormMailLogic::adminEmail(),
        ];
    }

    private static function hasWelcomeEmailColumn(): bool
    {
        return Schema::hasColumn('mentors', 'welcome_email_sent_at');
    }

    private static function resolveUserEmail(User $user): ?string
    {
        $email = strtolower(trim((string) ($user->email ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function firstName(Mentor $mentor, User $user): string
    {
        $fromUser = trim((string) ($user->f_name ?? ''));
        if ($fromUser !== '') {
            return $fromUser;
        }

        $fromDisplay = trim((string) ($mentor->display_name ?? ''));
        if ($fromDisplay !== '') {
            $parts = preg_split('/\s+/', $fromDisplay) ?: [];

            return $parts[0] ?? 'there';
        }

        $fromName = trim((string) ($user->name ?? ''));
        if ($fromName !== '') {
            $parts = preg_split('/\s+/', $fromName) ?: [];

            return $parts[0] ?? 'there';
        }

        return 'there';
    }

    private static function loginMediumLabel(?string $medium): string
    {
        return match (strtolower((string) $medium)) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'apple' => 'Apple',
            default => 'MentorKhoj',
        };
    }
}
