<?php

namespace Tests\Unit;

use App\CentralLogics\MentorImageService;
use App\Model\Mentor\Mentor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MentorImageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_resolve_public_filenames_returns_db_filename_when_disk_missing(): void
    {
        $mentor = new Mentor([
            'images' => json_encode(['2026-07-06-6a4ad02f35603.png']),
            'legacy_product_id' => null,
        ]);

        $result = MentorImageService::resolvePublicFilenames($mentor);

        $this->assertSame(['2026-07-06-6a4ad02f35603.png'], $result);
    }

    public function test_resolve_public_filenames_returns_disk_file_when_present(): void
    {
        Storage::disk('public')->put('product/2026-07-06-abc.png', 'photo-bytes');

        $mentor = new Mentor([
            'images' => json_encode(['2026-07-06-abc.png']),
            'legacy_product_id' => null,
        ]);

        $result = MentorImageService::resolvePublicFilenames($mentor);

        $this->assertSame(['2026-07-06-abc.png'], $result);
        $this->assertTrue(MentorImageService::fileExists('2026-07-06-abc.png'));
    }

    public function test_apply_image_update_skips_when_no_upload_or_remove(): void
    {
        $mentor = new Mentor([
            'images' => json_encode(['keep.png']),
        ]);

        $result = MentorImageService::applyImageUpdate($mentor, [], []);

        $this->assertNull($result);
    }

    public function test_apply_image_update_merges_on_explicit_upload(): void
    {
        $mentor = new Mentor([
            'images' => json_encode(['keep.png']),
        ]);

        $file = UploadedFile::fake()->image('new-photo.jpg');
        $result = MentorImageService::applyImageUpdate($mentor, [], [$file]);

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertContains('keep.png', $decoded);
        $this->assertCount(2, $decoded);
    }

    public function test_apply_image_update_deletes_only_on_explicit_remove(): void
    {
        Storage::disk('public')->put('product/remove-me.png', 'x');

        $mentor = new Mentor([
            'images' => json_encode(['remove-me.png', 'keep.png']),
        ]);

        $result = MentorImageService::applyImageUpdate($mentor, ['remove-me.png'], []);

        $this->assertNotNull($result);
        $this->assertSame(['keep.png'], json_decode($result, true));
        Storage::disk('public')->assertMissing('product/remove-me.png');
    }

    public function test_stored_filenames_excludes_placeholders(): void
    {
        $this->assertSame(
            ['real.png'],
            MentorImageService::storedFilenames(['default.png', 'real.png', 'def.png']),
        );
    }

    public function test_mentor_controllers_do_not_use_destructive_image_wipe_pattern(): void
    {
        $files = [
            base_path('app/Http/Controllers/Api/V1/Mentor/MentorDashboardController.php'),
            base_path('app/Http/Controllers/Admin/MentorController.php'),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString(
                '$existing !== $mentor->images_array',
                $contents,
                basename($file) . ' must not reintroduce automatic image wipe on profile save.',
            );
            $this->assertStringNotContainsString(
                'existingFilenames($mentor->images_array)',
                $contents,
                basename($file) . ' must use applyImageUpdate() instead of disk-filtered existing filenames.',
            );
            $this->assertStringContainsString(
                'applyImageUpdate',
                $contents,
                basename($file) . ' must route image updates through applyImageUpdate().',
            );
        }
    }
}
