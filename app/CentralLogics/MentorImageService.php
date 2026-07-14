<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;
use App\Model\Product;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MentorImageService
{
    private const DISK = 'public';
    private const DIR = 'product/';

    /**
     * Upload new mentor profile images to the same product/ folder as legacy mentors.
     *
     * @param UploadedFile[] $files
     * @return string[]
     */
    public static function uploadMany(array $files): array
    {
        $names = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $ext = 'png';
            }

            $imageName = Carbon::now()->toDateString() . '-' . uniqid() . '.' . $ext;
            $path = self::DIR . $imageName;
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false) {
                throw new \RuntimeException('Unable to read uploaded photo.');
            }

            Storage::disk(self::DISK)->put($path, $contents);
            if (!Storage::disk(self::DISK)->exists($path)) {
                throw new \RuntimeException('Photo upload failed — file was not saved on the server.');
            }

            $names[] = $imageName;
        }

        return $names;
    }

    public static function deleteFilename(string $filename): void
    {
        if ($filename && !in_array($filename, ['default.png', 'def.png'], true)) {
            Helpers::delete(self::DIR . $filename);
        }
    }

    public static function fileExists(?string $filename): bool
    {
        if (!$filename || in_array($filename, ['default.png', 'def.png'], true)) {
            return false;
        }

        return Storage::disk(self::DISK)->exists(self::DIR . $filename);
    }

    /**
     * @param string[] $files
     * @return string[]
     */
    public static function existingFilenames(array $files): array
    {
        return array_values(array_filter($files, fn ($filename) => self::fileExists($filename)));
    }

    /**
     * Mentor photos for public API — mentor uploads first, then legacy product images.
     *
     * @return string[]
     */
    public static function resolvePublicFilenames(Mentor $mentor): array
    {
        $existing = self::existingFilenames($mentor->images_array);
        if (!empty($existing)) {
            return $existing;
        }

        if (!$mentor->legacy_product_id) {
            return [];
        }

        $product = Product::find($mentor->legacy_product_id);
        if (!$product || empty($product->image)) {
            return [];
        }

        $images = $product->image;
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [$images];
        }

        return self::existingFilenames(is_array($images) ? $images : []);
    }

    public static function firstPublicFilename(Mentor $mentor): ?string
    {
        $files = self::resolvePublicFilenames($mentor);

        return $files[0] ?? null;
    }

    public static function publicAssetUrl(?string $filename): ?string
    {
        if (!self::fileExists($filename)) {
            return null;
        }

        return asset('storage/app/public/' . self::DIR . $filename);
    }

    public static function apiPhotoUrl(Mentor $mentor): ?string
    {
        if (!self::firstPublicFilename($mentor)) {
            return null;
        }

        $slug = $mentor->username ?: (string) $mentor->id;

        return url('/api/v1/mentors/' . rawurlencode($slug) . '/photo');
    }

    public static function streamFirstPhoto(Mentor $mentor): ?StreamedResponse
    {
        $filename = self::firstPublicFilename($mentor);
        if (!$filename) {
            return null;
        }

        $path = self::DIR . $filename;

        return Storage::disk(self::DISK)->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** @return array{url: string, missing: bool, filename: ?string} */
    public static function adminListThumbnail(Mentor $mentor): array
    {
        $filename = self::firstPublicFilename($mentor);
        $placeholder = asset('public/assets/admin/img/400x400/img2.jpg');

        if (!$filename) {
            $first = $mentor->images_array[0] ?? null;
            $hasBrokenRef = $first && !in_array($first, ['default.png', 'def.png'], true);

            return [
                'url' => $placeholder,
                'missing' => $hasBrokenRef,
                'filename' => $first,
            ];
        }

        return [
            'url' => self::publicAssetUrl($filename) ?? $placeholder,
            'missing' => false,
            'filename' => $filename,
        ];
    }

    /**
     * Merge existing filenames with new uploads, respecting removals.
     *
     * @param string[] $existing
     * @param string[] $remove
     * @param UploadedFile[] $newFiles
     * @return string[]
     */
    public static function merge(array $existing, array $remove, array $newFiles): array
    {
        $kept = array_values(array_filter($existing, fn ($f) => !in_array($f, $remove, true)));
        foreach ($remove as $filename) {
            self::deleteFilename($filename);
        }
        $uploaded = self::uploadMany($newFiles);

        return array_values(array_slice(array_merge($kept, $uploaded), 0, 4));
    }
}
