<?php

namespace App\CentralLogics;

use App\Model\BusinessSetting;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorService;
use App\Model\Mentor\MentorSetting;
use App\Model\Mentor\MentorShareLog;
use App\Model\Review;
use Illuminate\Support\Str;

class MentorLogic
{
    public static function slugifyUsername(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'mentor';
        }
        return $base;
    }

    public static function uniqueUsername(string $base, ?int $excludeId = null): string
    {
        $username = self::slugifyUsername($base);
        $candidate = $username;
        $i = 1;
        while (Mentor::where('username', $candidate)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $candidate = $username . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    public static function resolveMentor(string $usernameOrId): ?Mentor
    {
        if (ctype_digit($usernameOrId)) {
            $mentor = Mentor::find((int) $usernameOrId);
            if ($mentor) {
                return $mentor;
            }
        }

        if (preg_match('/^(\d+)-/', $usernameOrId, $m)) {
            $mentor = Mentor::where('legacy_product_id', (int) $m[1])->first();
            if ($mentor) {
                return $mentor;
            }
        }

        return Mentor::where('username', $usernameOrId)->first();
    }

    public static function profileUrl(Mentor $mentor): string
    {
        $site = env('APP_URL', 'https://www.mentorkhoj.com');
        return rtrim($site, '/') . '/mentor/' . $mentor->username;
    }

    /** @return array<string, string> */
    public static function normalizeSocialLinks(?array $links): array
    {
        $allowed = ['facebook', 'instagram', 'linkedin', 'youtube', 'website'];
        $out = [];
        foreach ($allowed as $key) {
            $url = trim((string) ($links[$key] ?? ''));
            if ($url !== '') {
                $out[$key] = $url;
            }
        }
        return $out;
    }

    public static function platformFeePercent(): float
    {
        $setting = BusinessSetting::where('key', 'mentor_platform_fee_percent')->first();
        return $setting ? (float) $setting->value : 10.0;
    }

    public static function formatPublic(Mentor $mentor, bool $includeDisabledServices = false): array
    {
        $services = $includeDisabledServices
            ? $mentor->services
            : $mentor->enabledServices;

        return [
            'id' => $mentor->id,
            'username' => $mentor->username,
            'name' => $mentor->display_name,
            'display_name' => $mentor->display_name,
            'headline' => $mentor->headline,
            'description' => $mentor->bio_html,
            'image' => $mentor->images_array,
            'category_ids' => $mentor->category_ids_array,
            'discount' => $mentor->profile_discount,
            'discount_type' => $mentor->discount_type,
            'is_published' => $mentor->is_published,
            'view_count' => $mentor->view_count,
            'profile_url' => self::profileUrl($mentor),
            'social_links' => $mentor->social_links_array,
            'rating' => self::ratingSummary($mentor),
            'active_reviews' => self::reviewsPreview($mentor),
            'services' => $services->map(fn ($s) => self::formatService($s))->values()->all(),
        ];
    }

    public static function formatService(MentorService $service): array
    {
        return [
            'id' => $service->id,
            'title' => $service->title,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'price' => $service->price,
            'compare_at_price' => $service->compare_at_price,
            'badge' => $service->badge,
            'is_enabled' => $service->is_enabled,
            'is_popular' => $service->is_popular,
            'meeting_type' => $service->meeting_type,
            'sort_order' => $service->sort_order,
        ];
    }

    public static function ratingSummary(Mentor $mentor): array
    {
        if (!$mentor->legacy_product_id) {
            return [['average' => '0', 'product_id' => $mentor->id]];
        }
        $avg = Review::where('product_id', $mentor->legacy_product_id)
            ->where('is_active', 1)
            ->avg('rating');
        return [['average' => (string) round($avg ?: 0, 1), 'product_id' => $mentor->legacy_product_id]];
    }

    public static function reviewsPreview(Mentor $mentor, int $limit = 6): array
    {
        if (!$mentor->legacy_product_id) {
            return [];
        }
        return Review::where('product_id', $mentor->legacy_product_id)
            ->where('is_active', 1)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'customer_name' => $r->customer_name ?? 'Mentee',
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public static function setupChecklist(Mentor $mentor): array
    {
        $settings = $mentor->settings ?? MentorSetting::firstOrCreate(['mentor_id' => $mentor->id]);
        $payout = json_decode($settings->payout_details ?? '{}', true) ?: [];
        $hasShare = MentorShareLog::where('mentor_id', $mentor->id)->exists();

        return [
            ['key' => 'availability', 'label' => 'Set availability', 'done' => false],
            ['key' => 'customize', 'label' => 'Customize your mentor page', 'done' => count($mentor->images_array) > 0 && !empty($mentor->bio_html) && !empty($mentor->username)],
            ['key' => 'services', 'label' => 'Add session offerings', 'done' => $mentor->enabledServices()->count() > 0],
            ['key' => 'payouts', 'label' => 'Set up payouts', 'done' => !empty($payout['account_number'] ?? $payout['upi_id'] ?? null)],
            ['key' => 'share', 'label' => 'Share your page', 'done' => $hasShare],
            ['key' => 'social', 'label' => 'Add social media links', 'done' => count($mentor->social_links_array) > 0],
            ['key' => 'publish', 'label' => 'Publish page', 'done' => (bool) $mentor->is_published],
        ];
    }

    public static function checklistProgress(array $items): int
    {
        if (count($items) === 0) {
            return 0;
        }
        $done = count(array_filter($items, fn ($i) => $i['done']));
        return (int) round(($done / count($items)) * 100);
    }
}
