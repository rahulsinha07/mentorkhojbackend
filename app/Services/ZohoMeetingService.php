<?php

namespace App\Services;

use App\CentralLogics\SessionCreditLogic;
use App\Model\Mentor\MentorBooking;
use App\Model\ZohoSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the Zoho Meeting API (.in data center) behind a single shared
 * MentorKhoj-owned Zoho account. Credentials and tokens live in the
 * zoho_settings singleton row, edited from System Settings.
 *
 * Every public method here is safe to call even when Zoho isn't configured
 * or connected yet — callers should check isConnected() first, and
 * createMeeting()/deleteMeeting() never throw, they return null/false and
 * log, so a Zoho outage never blocks a booking.
 */
class ZohoMeetingService
{
    private const ACCOUNTS_BASE = 'https://accounts.zoho.in';
    private const MEETING_BASE = 'https://meeting.zoho.in/api/v2';
    // manageOrg.READ is needed to look up the account's zsoid (org id) and
    // zuid (user id), both required on every other Meeting API call.
    private const SCOPE = 'ZohoMeeting.meeting.ALL,ZohoMeeting.manageOrg.READ';

    public function isConfigured(): bool
    {
        return ZohoSetting::current()->isConfigured();
    }

    public function isConnected(): bool
    {
        return ZohoSetting::current()->isConnected();
    }

    public function getAuthorizationUrl(string $redirectUri): string
    {
        $setting = ZohoSetting::current();

        $query = http_build_query([
            'scope' => self::SCOPE,
            'client_id' => $setting->client_id,
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $redirectUri,
            'prompt' => 'consent',
        ]);

        return self::ACCOUNTS_BASE.'/oauth/v2/auth?'.$query;
    }

    /**
     * Exchanges the OAuth "code" from the callback for an access + refresh
     * token pair and persists them. Throws on failure — the controller is
     * expected to catch this and flash the error, since this only runs
     * during the interactive admin "Connect" click.
     */
    public function handleOAuthCallback(string $code, string $redirectUri, ?int $adminId = null): void
    {
        $setting = ZohoSetting::current();

        $response = Http::asForm()->post(self::ACCOUNTS_BASE.'/oauth/v2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $json = $response->json();

        if (!$response->successful() || empty($json['access_token'])) {
            $message = (string) ($json['error'] ?? $response->body());
            $setting->status = 'error';
            $setting->last_error = 'OAuth token exchange failed: '.$message;
            $setting->save();

            throw new \RuntimeException($setting->last_error);
        }

        $setting->access_token = $json['access_token'];
        $setting->refresh_token = $json['refresh_token'] ?? $setting->refresh_token;
        $setting->access_token_expires_at = now()->addSeconds((int) ($json['expires_in'] ?? 3600));
        $setting->status = 'connected';
        $setting->last_error = null;
        $setting->connected_at = now();
        $setting->connected_by_admin_id = $adminId;
        $setting->save();

        $this->fetchAndStoreOrgInfo($setting->access_token);
    }

    /**
     * Looks up the account's zsoid (organization id) and zuid (user id) via
     * GET user.json — every other Meeting API call needs these. Throws on
     * failure since this only runs during the interactive Connect step.
     */
    private function fetchAndStoreOrgInfo(string $accessToken): void
    {
        $setting = ZohoSetting::current();

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
        ])->get(self::MEETING_BASE.'/user.json');

        $json = $response->json();

        if (!$response->successful() || empty($json['userDetails']['zsoid'])) {
            $setting->status = 'error';
            $setting->last_error = 'Connected, but could not look up Zoho organization id: '.mb_substr($response->body(), 0, 500);
            $setting->save();

            throw new \RuntimeException($setting->last_error);
        }

        $setting->zoho_account_id = (string) $json['userDetails']['zsoid'];
        $setting->zoho_presenter_zuid = (string) ($json['userDetails']['zuid'] ?? '');
        $setting->save();
    }

    /**
     * Returns a usable access token, refreshing it first if it's expiring
     * soon. Returns null (and marks status=error) if refresh fails.
     */
    public function refreshAccessTokenIfNeeded(): ?string
    {
        $setting = ZohoSetting::current();

        if (!$setting->isConnected()) {
            return null;
        }

        $expiresAt = $setting->access_token_expires_at;
        if ($setting->access_token && $expiresAt && $expiresAt->gt(now()->addMinutes(2))) {
            return $setting->access_token;
        }

        $response = Http::asForm()->post(self::ACCOUNTS_BASE.'/oauth/v2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
            'refresh_token' => $setting->refresh_token,
        ]);

        $json = $response->json();

        if (!$response->successful() || empty($json['access_token'])) {
            $message = (string) ($json['error'] ?? $response->body());
            $setting->status = 'error';
            $setting->last_error = 'Token refresh failed: '.$message;
            $setting->save();

            Log::error('Zoho access token refresh failed', ['response' => $json ?: $response->body()]);

            return null;
        }

        $setting->access_token = $json['access_token'];
        $setting->access_token_expires_at = now()->addSeconds((int) ($json['expires_in'] ?? 3600));
        if (!empty($json['refresh_token'])) {
            $setting->refresh_token = $json['refresh_token'];
        }
        $setting->status = 'connected';
        $setting->last_error = null;
        $setting->save();

        return $setting->access_token;
    }

    /**
     * Creates a Zoho Meeting for the given booking.
     *
     * @return array{meeting_id: string, join_url: string}|null
     */
    public function createMeeting(MentorBooking $booking): ?array
    {
        // $booking->scheduled_at is Eloquent's datetime cast, which relabels
        // the UTC-stored instant using config('app.timezone') (Asia/Calcutta)
        // on read — using it directly here would send Zoho a startTime
        // shifted hours off from when the session actually is. sessionDateTime()
        // re-tags the raw value as UTC correctly; see SessionCreditLogic for
        // the full explanation.
        return $this->createMeetingRaw(
            topic: $this->topicFor($booking),
            startTime: SessionCreditLogic::sessionDateTime($booking) ?? now(),
            durationMinutes: (int) ($booking->duration_minutes ?: 30)
        );
    }

    /**
     * Creates a short-lived throwaway meeting, used by the admin "Test
     * Connection" action. Caller is responsible for deleting it afterwards.
     *
     * @return array{meeting_id: string, join_url: string}|null
     */
    public function createTestMeeting(): ?array
    {
        return $this->createMeetingRaw(
            topic: 'MentorKhoj — Zoho connection test',
            startTime: now()->addMinutes(5),
            durationMinutes: 15
        );
    }

    /**
     * @return array{meeting_id: string, join_url: string}|null
     */
    private function createMeetingRaw(string $topic, \DateTimeInterface $startTime, int $durationMinutes): ?array
    {
        $token = $this->refreshAccessTokenIfNeeded();
        $setting = ZohoSetting::current();
        if (!$token || empty($setting->zoho_account_id) || empty($setting->zoho_presenter_zuid)) {
            return null;
        }

        $when = $startTime instanceof \Carbon\Carbon ? $startTime : \Carbon\Carbon::parse($startTime);
        // Zoho rejects a start time that isn't comfortably in the future — a
        // session scheduled for "right now" (or a few seconds in the past by
        // the time this request reaches Zoho) would otherwise fail with
        // INVALID_TIME. Clamp forward so same-moment bookings still work;
        // this only affects the metadata Zoho stores, not the booking's own
        // scheduled_at that mentors/mentees see.
        $minStart = now()->addMinutes(2);
        if ($when->lt($minStart)) {
            $when = $minStart;
        }
        // format() renders in whatever timezone the Carbon instance carries
        // (sessionDateTime() returns UTC); re-express it in the zone we tell
        // Zoho below so the wall-clock digits we send actually match it.
        $whenLocal = $when->copy()->timezone(config('app.timezone', 'Asia/Kolkata'));

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$token,
                'Content-Type' => 'application/json;charset=UTF-8',
            ])->post(self::MEETING_BASE.'/'.$setting->zoho_account_id.'/sessions.json', [
                'session' => [
                    'topic' => $topic,
                    'presenter' => (int) $setting->zoho_presenter_zuid,
                    'startTime' => $whenLocal->format('M j, Y h:i A'),
                    'duration' => $durationMinutes * 60 * 1000,
                    'timezone' => config('app.timezone', 'Asia/Kolkata'),
                ],
            ]);

            $json = $response->json();

            if (!$response->successful()) {
                $this->recordError('Zoho createMeeting failed (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 500));

                return null;
            }

            $meetingId = (string) (data_get($json, 'session.meetingKey') ?: '');
            $joinUrl = (string) (data_get($json, 'session.joinLink') ?: '');

            if ($meetingId === '' || $joinUrl === '') {
                $this->recordError('Zoho createMeeting succeeded but response shape was unexpected: '.mb_substr($response->body(), 0, 500));

                return null;
            }

            return ['meeting_id' => $meetingId, 'join_url' => $joinUrl];
        } catch (\Throwable $e) {
            $this->recordError('Zoho createMeeting threw: '.$e->getMessage());

            return null;
        }
    }

    public function deleteMeeting(string $zohoMeetingId): bool
    {
        $token = $this->refreshAccessTokenIfNeeded();
        $setting = ZohoSetting::current();
        if (!$token || $zohoMeetingId === '' || empty($setting->zoho_account_id)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken '.$token,
            ])->delete(self::MEETING_BASE.'/'.$setting->zoho_account_id.'/sessions/'.$zohoMeetingId.'.json');

            if (!$response->successful()) {
                Log::error('Zoho deleteMeeting failed', [
                    'meeting_id' => $zohoMeetingId,
                    'http' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Zoho deleteMeeting threw', ['meeting_id' => $zohoMeetingId, 'message' => $e->getMessage()]);

            return false;
        }
    }

    public function disconnect(): void
    {
        $setting = ZohoSetting::current();
        $setting->access_token = null;
        $setting->refresh_token = null;
        $setting->access_token_expires_at = null;
        $setting->status = 'disconnected';
        $setting->last_error = null;
        $setting->save();
    }

    private function topicFor(MentorBooking $booking): string
    {
        $booking->loadMissing(['mentor', 'mentee']);
        $mentorName = trim((string) ($booking->mentor?->display_name ?? 'Mentor'));
        $menteeName = trim(($booking->mentee?->f_name ?? '').' '.($booking->mentee?->l_name ?? ''));
        $menteeName = $menteeName !== '' ? $menteeName : 'Student';

        return "MentorKhoj session — {$mentorName} & {$menteeName}";
    }

    private function recordError(string $message): void
    {
        Log::error($message);
        $setting = ZohoSetting::current();
        $setting->last_error = $message;
        $setting->save();
    }
}
