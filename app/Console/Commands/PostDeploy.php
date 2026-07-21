<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

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

        $checks = [
            'seminar_bookings_table' => Schema::hasTable('seminar_bookings'),
            'mentor_welcome_email_column' => Schema::hasColumn('mentors', 'welcome_email_sent_at'),
            'mentor_booking_email_columns' => Schema::hasColumn('mentor_bookings', 'mentee_booked_email_sent_at'),
        ];

        $this->newLine();
        $this->info('Deployment checks:');
        foreach ($checks as $label => $ok) {
            $this->line(sprintf('  [%s] %s', $ok ? 'OK' : 'MISSING', $label));
        }

        if (in_array(false, $checks, true)) {
            $this->warn('Some database objects are still missing. Review migration output above.');

            return self::FAILURE;
        }

        $this->info('Post-deploy complete.');

        return self::SUCCESS;
    }
}
