<?php

namespace App\CentralLogics;

use App\Model\Internship\Internship;
use App\Model\Internship\InternshipApplication;
use Illuminate\Support\Str;

class InternshipLogic
{
    public static function uniqueSlug(string $role, ?int $excludeId = null): string
    {
        $base = Str::slug($role);
        if ($base === '') {
            $base = 'internship';
        }
        $candidate = $base;
        $i = 1;
        while (Internship::where('slug', $candidate)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    public static function resolveBySlug(string $slug): ?Internship
    {
        return Internship::where('slug', $slug)->first();
    }

    public static function resolveByRole(string $role): ?Internship
    {
        return Internship::where('role', $role)->first();
    }

    public static function generateApplicationId(): string
    {
        return 'INT-' . now()->format('Ymd-His') . '-' . random_int(1000, 9999);
    }

    /** @return array<string, mixed> */
    public static function formatPublic(Internship $internship): array
    {
        return [
            'id' => $internship->id,
            'slug' => $internship->slug,
            'role' => $internship->role,
            'team' => $internship->team,
            'location' => $internship->location,
            'type' => $internship->type,
            'duration' => $internship->duration,
            'stipend' => $internship->stipend,
            'blurb' => $internship->blurb,
            'skills' => $internship->skills ?? [],
            'status' => $internship->status,
            'accepting_applications' => $internship->status === 'active' && $internship->is_published,
        ];
    }

    /** @return array<string, mixed> */
    public static function formatApplication(InternshipApplication $application): array
    {
        return [
            'id' => $application->application_id,
            'internship_id' => $application->internship_id,
            'name' => $application->name,
            'email' => $application->email,
            'phone' => $application->phone,
            'org' => $application->org,
            'role' => $application->role,
            'resume_url' => $application->resume_url,
            'message' => $application->message,
            'status' => $application->status,
            'created_at' => $application->created_at?->toIso8601String(),
        ];
    }

    /** @param array<int, string>|string|null $skills */
    public static function parseSkills($skills): array
    {
        if (is_array($skills)) {
            return array_values(array_filter(array_map('trim', $skills)));
        }
        if (!is_string($skills) || trim($skills) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $skills))));
    }
}
