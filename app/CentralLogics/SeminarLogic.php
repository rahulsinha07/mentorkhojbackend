<?php

namespace App\CentralLogics;

use App\Model\Seminar\Seminar;
use App\Model\Seminar\SeminarRegistration;
use Illuminate\Support\Str;

class SeminarLogic
{
    public static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'seminar';
        }
        $candidate = $base;
        $i = 1;
        while (Seminar::where('slug', $candidate)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    public static function resolveBySlug(string $slug): ?Seminar
    {
        return Seminar::where('slug', $slug)->first();
    }

    public static function generateRegistrationId(): string
    {
        return 'SEM-' . now()->format('Ymd-His') . '-' . random_int(1000, 9999);
    }

    /** @return array<string, mixed> */
    public static function formatPublic(Seminar $seminar): array
    {
        return [
            'id' => $seminar->id,
            'slug' => $seminar->slug,
            'title' => $seminar->title,
            'tagline' => $seminar->tagline,
            'blurb' => $seminar->blurb,
            'date' => $seminar->date,
            'mode' => $seminar->mode,
            'duration' => $seminar->duration,
            'audience' => $seminar->audience,
            'emoji' => $seminar->emoji,
            'highlights' => $seminar->highlights ?? [],
            'status' => $seminar->status,
            'accepting_registrations' => $seminar->status === 'active' && $seminar->is_published,
            'fee_amount' => (float) ($seminar->fee_amount ?? 0),
            'currency' => $seminar->currency ?? 'INR',
            'is_free' => ((float) ($seminar->fee_amount ?? 0)) <= 0,
        ];
    }

    /** @return array<string, mixed> */
    public static function formatRegistration(SeminarRegistration $registration): array
    {
        return [
            'id' => $registration->registration_id,
            'seminar_id' => $registration->seminar_id,
            'name' => $registration->name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'college' => $registration->college,
            'details' => $registration->details,
            'status' => $registration->status,
            'created_at' => $registration->created_at?->toIso8601String(),
        ];
    }

    /** @param array<int, string> $highlights */
    public static function parseHighlights(?string $raw): array
    {
        if (!$raw) {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        return array_values(array_filter(array_map('trim', $lines)));
    }
}
