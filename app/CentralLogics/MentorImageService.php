<?php

namespace App\CentralLogics;

use Illuminate\Http\UploadedFile;

class MentorImageService
{
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
            if ($file instanceof UploadedFile) {
                $names[] = Helpers::upload('product/', 'png', $file);
            }
        }
        return $names;
    }

    public static function deleteFilename(string $filename): void
    {
        if ($filename && $filename !== 'default.png') {
            Helpers::delete('product/' . $filename);
        }
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
