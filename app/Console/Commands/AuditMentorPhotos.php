<?php

namespace App\Console\Commands;

use App\CentralLogics\MentorImageService;
use App\Model\Mentor\Mentor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditMentorPhotos extends Command
{
    protected $signature = 'mentorkhoj:audit-mentor-photos';

    protected $description = 'Report mentor profile photos: DB filenames vs files on disk';

    public function handle(): int
    {
        $missing = 0;
        $mentors = Mentor::with('user')->orderBy('id')->get(['id', 'username', 'display_name', 'images', 'user_id']);

        $this->info('Mentor photo audit');
        $this->newLine();

        foreach ($mentors as $mentor) {
            $stored = MentorImageService::storedFilenames($mentor->images_array);
            if (empty($stored)) {
                $this->line(sprintf('  [%s] #%d %s — no photo in DB (placeholder only)', 'SKIP', $mentor->id, $mentor->username));
                continue;
            }

            foreach ($stored as $filename) {
                $onDisk = MentorImageService::fileExists($filename);
                $status = $onDisk ? 'OK' : 'MISSING';
                if (!$onDisk) {
                    $missing++;
                }
                $this->line(sprintf('  [%s] #%d %s — %s', $status, $mentor->id, $mentor->username, $filename));
            }

            if ($mentor->user_id) {
                $user = $mentor->user;
                if ($user && $user->image && !in_array($user->image, ['def.png', 'default.png'], true)) {
                    $profilePath = 'profile/' . $user->image;
                    $profileOk = Storage::disk('public')->exists($profilePath);
                    $this->line(sprintf(
                        '         LinkedIn/profile fallback: %s (%s)',
                        $user->image,
                        $profileOk ? 'on disk' : 'not on disk',
                    ));
                }
            }
        }

        $this->newLine();
        if ($missing > 0) {
            $this->warn("{$missing} mentor photo file(s) missing on disk — restore from backup or re-upload in admin.");

            return self::FAILURE;
        }

        $this->info('All mentor photos referenced in DB exist on disk.');

        return self::SUCCESS;
    }
}
