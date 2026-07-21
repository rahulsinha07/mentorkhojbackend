<?php

namespace App\CentralLogics;

use App\Mail\Form\FormSubmissionMail;
use App\Model\Internship\Internship;
use App\Model\Internship\InternshipApplication;
use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarRegistration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class FormMailLogic
{
    public static function isMailEnabled(): bool
    {
        $mailConfig = Helpers::get_business_settings('mail_config');
        if (is_array($mailConfig) && ($mailConfig['status'] ?? 0) == 1) {
            return !empty($mailConfig['host']) && !empty($mailConfig['email_id']);
        }

        return !empty(env('MAIL_HOST')) && (!empty(env('MAIL_USERNAME')) || env('MAIL_MAILER') === 'sendmail');
    }

    public static function adminEmail(): string
    {
        $fromEnv = trim((string) config('app.mentorkhoj_admin_email', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $mailConfig = Helpers::get_business_settings('mail_config');
        if (is_array($mailConfig) && !empty($mailConfig['email_id'])) {
            return $mailConfig['email_id'];
        }

        return 'admin@mentorkhoj.com';
    }

    /** @return array<string, string> */
    public static function brandContext(): array
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
        $logoUrl = self::brandLogoUrl();

        return [
            'site_name' => 'MentorKhoj',
            'site_url' => $siteUrl,
            'logo_url' => $logoUrl,
            'admin_email' => self::adminEmail(),
            'tagline' => 'Find mentors. Book sessions. Grow faster.',
            'seminars_url' => $siteUrl . '/seminars',
            'internships_url' => $siteUrl . '/internships',
            'mentors_url' => $siteUrl . '/mentors',
        ];
    }

    public static function brandLogoUrl(): string
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');
        $mentorkhojLogo = $siteUrl . '/logo-512.png';

        try {
            $logoSetting = Helpers::get_business_settings('logo');
            if (!empty($logoSetting)) {
                return asset('storage/app/public/restaurant/' . $logoSetting);
            }
        } catch (\Throwable $e) {
        }

        return $mentorkhojLogo;
    }

    /** @param array<string, mixed> $data */
    public static function withBrandPublic(array $data): array
    {
        return self::withBrand($data);
    }

    /** @param array<string, mixed> $data */
    private static function withBrand(array $data): array
    {
        return array_merge($data, ['brand' => self::brandContext()]);
    }

    public static function sendSeminarRegistrationEmails(
        Seminar $seminar,
        SeminarRegistration $registration
    ): bool {
        if (!self::isMailEnabled()) {
            Log::warning('Form mail skipped: SMTP not configured (enable mail in admin or set MAIL_* in .env)');

            return false;
        }

        $userSent = false;
        $seminarData = self::seminarDetails($seminar);
        $registrationData = self::registrationDetails($registration);

        try {
            Mail::to($registration->email)
                ->cc(self::adminEmail())
                ->send(new FormSubmissionMail(
                    "You're registered — {$seminar->title} | MentorKhoj",
                    'email-templates.form.seminar-registration-user',
                    self::withBrand([
                        'name' => $registration->name,
                        'seminar' => $seminarData,
                        'registration' => $registrationData,
                    ])
                ));
            $userSent = true;
        } catch (\Throwable $e) {
            Log::warning('Seminar user email failed: ' . $e->getMessage());
        }

        try {
            Mail::to(self::adminEmail())->send(new FormSubmissionMail(
                "[MentorKhoj] New seminar registration — {$seminar->title}",
                'email-templates.form.seminar-registration-admin',
                self::withBrand([
                    'seminar' => $seminarData,
                    'registration' => $registrationData,
                ])
            ));
        } catch (\Throwable $e) {
            Log::warning('Seminar admin email failed: ' . $e->getMessage());
        }

        return $userSent;
    }

    public static function sendPasswordResetEmail(
        string $toEmail,
        string $name,
        string $token,
        string $resetLink,
    ): bool {
        if (!self::isMailEnabled()) {
            Log::warning('Password reset mail skipped: SMTP not configured (set MAIL_* in .env or enable admin Mail Config)');

            return false;
        }

        try {
            Mail::to($toEmail)->send(new \App\Mail\Customer\PasswordReset($token, $name, $resetLink));

            return true;
        } catch (\Throwable $e) {
            Log::error('Password reset email failed: ' . $e->getMessage(), ['email' => $toEmail]);

            return false;
        }
    }

    public static function sendInternshipApplicationEmails(
        ?Internship $internship,
        InternshipApplication $application
    ): bool {
        if (!self::isMailEnabled()) {
            Log::warning('Form mail skipped: SMTP not configured (enable mail in admin or set MAIL_* in .env)');

            return false;
        }

        $userSent = false;
        $internshipData = $internship ? self::internshipDetails($internship) : null;
        $applicationData = self::applicationDetails($application);

        try {
            Mail::to($application->email)
                ->cc(self::adminEmail())
                ->send(new FormSubmissionMail(
                    'We received your internship application | MentorKhoj',
                    'email-templates.form.internship-application-user',
                    self::withBrand([
                        'name' => $application->name,
                        'internship' => $internshipData,
                        'application' => $applicationData,
                    ])
                ));
            $userSent = true;
        } catch (\Throwable $e) {
            Log::warning('Internship user email failed: ' . $e->getMessage());
        }

        try {
            Mail::to(self::adminEmail())->send(new FormSubmissionMail(
                "[MentorKhoj] New internship application — {$application->role}",
                'email-templates.form.internship-application-admin',
                self::withBrand([
                    'internship' => $internshipData,
                    'application' => $applicationData,
                ])
            ));
        } catch (\Throwable $e) {
            Log::warning('Internship admin email failed: ' . $e->getMessage());
        }

        return $userSent;
    }

    /** @return array<string, mixed> */
    private static function seminarDetails(Seminar $seminar): array
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return [
            'title' => $seminar->title,
            'tagline' => $seminar->tagline,
            'blurb' => $seminar->blurb,
            'date' => $seminar->date,
            'mode' => $seminar->mode,
            'duration' => $seminar->duration,
            'audience' => $seminar->audience,
            'emoji' => $seminar->emoji,
            'highlights' => $seminar->highlights ?? [],
            'slug' => $seminar->slug,
            'page_url' => $siteUrl . '/seminars/' . $seminar->slug,
        ];
    }

    /** @return array<string, mixed> */
    private static function registrationDetails(SeminarRegistration $registration): array
    {
        return [
            'id' => $registration->registration_id,
            'name' => $registration->name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'college' => $registration->college,
            'details' => $registration->details,
            'status' => $registration->status,
            'submitted_at' => $registration->created_at?->format('d M Y, h:i A'),
        ];
    }

    /** @return array<string, mixed> */
    private static function internshipDetails(Internship $internship): array
    {
        $siteUrl = rtrim((string) config('app.mentorkhoj_site_url', 'https://www.mentorkhoj.com'), '/');

        return [
            'role' => $internship->role,
            'team' => $internship->team,
            'location' => $internship->location,
            'type' => $internship->type,
            'duration' => $internship->duration,
            'stipend' => $internship->stipend,
            'blurb' => $internship->blurb,
            'skills' => $internship->skills ?? [],
            'slug' => $internship->slug,
            'page_url' => $siteUrl . '/internships',
        ];
    }

    /** @return array<string, mixed> */
    private static function applicationDetails(InternshipApplication $application): array
    {
        return [
            'id' => $application->application_id,
            'name' => $application->name,
            'email' => $application->email,
            'phone' => $application->phone,
            'org' => $application->org,
            'role' => $application->role,
            'resume_url' => $application->resume_url,
            'message' => $application->message,
            'status' => $application->status,
            'submitted_at' => $application->created_at?->format('d M Y, h:i A'),
        ];
    }
}
