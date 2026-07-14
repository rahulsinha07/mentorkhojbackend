<?php

namespace App\CentralLogics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentorKhojRevalidateLogic
{
    /**
     * Ping MentorKhoj on-demand revalidation for one or more paths.
     * No-op when MENTORKHOJ_REVALIDATE_URL or MENTORKHOJ_REVALIDATE_SECRET is unset.
     *
     * @param  array<int, string>  $paths
     */
    public static function revalidatePaths(array $paths): void
    {
        $url = trim((string) env('MENTORKHOJ_REVALIDATE_URL', ''));
        $secret = trim((string) env('MENTORKHOJ_REVALIDATE_SECRET', ''));

        if ($url === '' || $secret === '') {
            return;
        }

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '' || $path[0] !== '/') {
                continue;
            }

            try {
                Http::timeout(5)->get($url, [
                    'secret' => $secret,
                    'path' => $path,
                ]);
            } catch (\Throwable $e) {
                Log::warning('MentorKhoj revalidate failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function revalidateSeminar(?string $slug = null): void
    {
        $paths = ['/seminars'];
        if ($slug) {
            $paths[] = '/seminars/' . ltrim($slug, '/');
        }
        self::revalidatePaths($paths);
    }

    public static function revalidateInternships(): void
    {
        self::revalidatePaths(['/internships']);
    }
}
