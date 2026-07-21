<?php

namespace App\CentralLogics;

use App\Model\Mentor\Mentor;

class MentorPhotoAuditLogic
{
    /** @return array<int, array{id: int, username: string, filename: string}> */
    public static function missingFiles(): array
    {
        $missing = [];

        foreach (Mentor::orderBy('id')->get(['id', 'username', 'images']) as $mentor) {
            foreach (MentorImageService::storedFilenames($mentor->images_array) as $filename) {
                if (!MentorImageService::fileExists($filename)) {
                    $missing[] = [
                        'id' => $mentor->id,
                        'username' => (string) $mentor->username,
                        'filename' => $filename,
                    ];
                }
            }
        }

        return $missing;
    }

    public static function allPhotosOnDisk(): bool
    {
        return count(self::missingFiles()) === 0;
    }
}
