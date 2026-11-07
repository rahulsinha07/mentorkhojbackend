<?php

namespace App\Services;

use App\Model\Mentor\MentorBooking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translates a session's Zoho-generated transcript into another language
 * using the Claude API, on demand, and caches the result on the booking so
 * the same language is never paid for twice.
 *
 * Zoho Meeting's own transcription only covers ~7 languages and has no
 * translation feature — this is a MentorKhoj-side addition on top of
 * whatever transcript_text Zoho gave us (see ZohoMeetingService::fetchRecording).
 */
class MeetingTranslationService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-5-5';

    /** ISO code => display name, kept short and reviewed by product — extend as needed. */
    public const SUPPORTED_LANGUAGES = [
        'hi' => 'Hindi',
        'en' => 'English',
        'bn' => 'Bengali',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'mr' => 'Marathi',
        'gu' => 'Gujarati',
        'kn' => 'Kannada',
        'ml' => 'Malayalam',
        'pa' => 'Punjabi',
        'ur' => 'Urdu',
    ];

    public function isConfigured(): bool
    {
        return !empty(config('services.anthropic.api_key'));
    }

    /**
     * Returns the cached translation if present, otherwise translates,
     * caches it on the booking, and returns it. Returns null (never throws)
     * if translation isn't configured, the language is unsupported, there's
     * no transcript yet, or the API call fails — callers should treat null
     * as "translation unavailable right now", not a hard error.
     */
    public function translateTranscript(MentorBooking $booking, string $languageCode): ?string
    {
        if (!isset(self::SUPPORTED_LANGUAGES[$languageCode])) {
            return null;
        }

        if (empty($booking->transcript_text)) {
            return null;
        }

        $cached = (array) ($booking->translated_transcripts ?? []);
        if (!empty($cached[$languageCode])) {
            return (string) $cached[$languageCode];
        }

        if (!$this->isConfigured()) {
            return null;
        }

        $languageName = self::SUPPORTED_LANGUAGES[$languageCode];

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 8000,
                'messages' => [[
                    'role' => 'user',
                    'content' => "Translate the following mentoring-session transcript into {$languageName}. ".
                        'Keep speaker labels and formatting if present. Output only the translated transcript, '.
                        "nothing else.\n\n---\n\n".$booking->transcript_text,
                ]],
            ]);

            if (!$response->successful()) {
                Log::error('Transcript translation failed', [
                    'booking_id' => $booking->id,
                    'language' => $languageCode,
                    'http' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $translated = trim((string) data_get($response->json(), 'content.0.text', ''));
            if ($translated === '') {
                return null;
            }

            $cached[$languageCode] = $translated;
            $booking->translated_transcripts = $cached;
            $booking->save();

            return $translated;
        } catch (\Throwable $e) {
            Log::error('Transcript translation threw', [
                'booking_id' => $booking->id,
                'language' => $languageCode,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
