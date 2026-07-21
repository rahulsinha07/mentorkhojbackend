<?php

namespace App\Console\Commands;

use App\CentralLogics\DeployHealthLogic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PostDeploy extends Command
{
    protected $signature = 'mentorkhoj:post-deploy';

    protected $description = 'Run migrations and clear caches after deploying MentorKhoj backend updates';

    public function handle(): int
    {
        $this->backupProductPhotos();

        $this->info('Running database migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(trim(Artisan::output()));

        $this->info('Clearing application caches...');
        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $command) {
            Artisan::call($command);
        }
        $this->line('Caches cleared.');

        $checks = DeployHealthLogic::checks();

        $this->newLine();
        $this->info('Deployment checks:');
        foreach ($checks as $label => $ok) {
            $this->line(sprintf('  [%s] %s', $ok ? 'OK' : 'MISSING', $label));
        }

        if (!DeployHealthLogic::ok()) {
            $this->warn('Some required database objects are still missing. Review migration output above.');

            return self::FAILURE;
        }

        if (empty($checks['razorpay_configured'])) {
            $this->warn('RazorPay is not configured — paid seminar bookings will fail until API keys are set in admin.');
        }

        $this->ensureDefaultMentorPlaceholder();

        $this->newLine();
        $this->info('Running mentor photo audit...');
        $auditExit = Artisan::call('mentorkhoj:audit-mentor-photos');
        $this->line(trim(Artisan::output()));

        if ($auditExit !== self::SUCCESS) {
            $this->error('Post-deploy blocked: mentor photo files are missing on disk.');
            $this->line('Restore from the latest backup in storage/app/backups/ or re-upload in Admin → Mentors.');

            return self::FAILURE;
        }

        $this->info('Post-deploy complete.');

        return self::SUCCESS;
    }

    private function backupProductPhotos(): void
    {
        $productDir = storage_path('app/public/product');
        if (!is_dir($productDir)) {
            $this->warn('Product photo folder not found — skipping backup.');

            return;
        }

        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $archive = $backupDir . '/product-' . date('Ymd-His') . '.tar.gz';
        $parent = dirname($productDir);
        $command = sprintf(
            'tar -czf %s -C %s product 2>/dev/null',
            escapeshellarg($archive),
            escapeshellarg($parent),
        );

        exec($command, $output, $exitCode);

        if ($exitCode === 0 && is_file($archive)) {
            $this->info('Backed up mentor photos to ' . $archive);
            $this->pruneOldBackups($backupDir, 5);

            return;
        }

        $this->warn('Could not create product photo backup — continue deploy manually only if you have another backup.');
    }

    private function pruneOldBackups(string $backupDir, int $keep): void
    {
        $files = glob($backupDir . '/product-*.tar.gz') ?: [];
        rsort($files);

        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }

    private function ensureDefaultMentorPlaceholder(): void
    {
        $target = storage_path('app/public/product/default.png');
        if (is_file($target)) {
            return;
        }

        $source = public_path('assets/admin/img/400x400/img2.jpg');
        if (!is_file($source)) {
            $this->warn('Mentor placeholder default.png is missing and no source image was found to copy.');

            return;
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        copy($source, $target);
        $this->line('Created storage/app/public/product/default.png placeholder.');
    }
}
