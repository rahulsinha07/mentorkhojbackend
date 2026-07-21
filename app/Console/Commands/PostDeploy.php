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
        Artisan::call('mentorkhoj:audit-mentor-photos');
        $this->line(trim(Artisan::output()));

        $this->info('Post-deploy complete.');

        return self::SUCCESS;
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