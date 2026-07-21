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

    private const PLACEHOLDERS = ['default.png', 'def.png'];

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
        if ($filename && !in_array($filename, self::PLACEHOLDERS, true)) {
            Helpers::delete(self::DIR . $filename);
        }
    }

    public static function fileExists(?string $filename): bool
    {
        if (!$filename || in_array($filename, self::PLACEHOLDERS, true)) {
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
     * Stored filenames from DB, excluding placeholders.
     *
     * @param string[] $files
     * @return string[]
     */
    public static function storedFilenames(array $files): array
    {
        return array_values(array_filter(
            $files,
            fn ($filename) => $filename && !in_array($filename, self::PLACEHOLDERS, true),
        ));
    }

    /**
     * Mentor photos for public API — mentor uploads first, then legacy product images.
     * Returns disk-verified filenames when available; falls back to DB filenames for display.
     *
     * @return string[]
     */
    public static function resolvePublicFilenames(Mentor $mentor): array
    {
        $stored = self::storedFilenames($mentor->images_array);
        $verified = self::existingFilenames($stored);
        if (!empty($verified)) {
            return $verified;
        }
        if (!empty($stored)) {
            return $stored;
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

        if (!is_array($images)) {
            return [];
        }

        $legacyStored = self::storedFilenames($images);
        $legacyVerified = self::existingFilenames($legacyStored);

        return !empty($legacyVerified) ? $legacyVerified : $legacyStored;
    }

    /** First filename that exists on disk (for streaming / photo_url). */
    public static function firstStreamableFilename(Mentor $mentor): ?string
    {
        foreach (self::resolvePublicFilenames($mentor) as $filename) {
            if (self::fileExists($filename)) {
                return $filename;
            }
        }

        return null;
    }

    public static function firstPublicFilename(Mentor $mentor): ?string
    {
        return self::firstStreamableFilename($mentor);
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
        if (!self::firstStreamableFilename($mentor)) {
            return null;
        }

        $slug = $mentor->username ?: (string) $mentor->id;

        return url('/api/v1/mentors/' . rawurlencode($slug) . '/photo');
    }

    public static function streamFirstPhoto(Mentor $mentor): ?StreamedResponse
    {
        $filename = self::firstStreamableFilename($mentor);
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
        $filename = self::firstStreamableFilename($mentor);
        $placeholder = asset('public/assets/admin/img/400x400/img2.jpg');

        if (!$filename) {
            $first = $mentor->images_array[0] ?? null;
            $hasBrokenRef = $first && !in_array($first, self::PLACEHOLDERS, true);

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
     * Only allowed path for updating mentors.images on profile save.
     * Returns null when no image change was requested (bio/social-only saves).
     *
     * @param UploadedFile[] $newFiles
     * @throws \RuntimeException
     */
    public static function applyImageUpdate(Mentor $mentor, array $remove, array $newFiles): ?string
    {
        $newFiles = array_values(array_filter($newFiles));
        $remove = array_values(array_filter($remove));

        if (empty($newFiles) && empty($remove)) {
            return null;
        }

        $merged = self::merge($mentor->images_array, $remove, $newFiles);

        return json_encode($merged ?: ['default.png']);
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
