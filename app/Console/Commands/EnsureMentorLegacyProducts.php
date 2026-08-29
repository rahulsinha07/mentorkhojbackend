<?php

namespace App\Console\Commands;

use App\CentralLogics\MentorLegacyProductLogic;
use App\Model\Mentor\Mentor;
use Illuminate\Console\Command;

class EnsureMentorLegacyProducts extends Command
{
    protected $signature = 'mentors:ensure-legacy-products
                            {--dry-run : Preview without writing}
                            {--mentor-id= : Only process one mentor id}
                            {--published : Only published mentors}';

    protected $description = 'Create legacy products for mentors missing legacy_product_id (required for online checkout)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mentorId = $this->option('mentor-id');

        $query = Mentor::query()->with('enabledServices')
            ->where(function ($q) {
                $q->whereNull('legacy_product_id')->orWhere('legacy_product_id', 0);
            });

        if ($this->option('published')) {
            $query->where('is_published', true);
        }
        if ($mentorId) {
            $query->where('id', (int) $mentorId);
        }

        $mentors = $query->orderBy('id')->get();
        if ($mentors->isEmpty()) {
            $this->info('No mentors need legacy products.');

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($mentors as $mentor) {
            if ($mentor->enabledServices->isEmpty()) {
                $this->warn("Skip #{$mentor->id} {$mentor->display_name}: no enabled services");

                continue;
            }

            if ($dryRun) {
                $this->line("Would create product for #{$mentor->id} {$mentor->display_name}");
                $created++;
                continue;
            }

            $productId = MentorLegacyProductLogic::ensureForMentor($mentor);
            if ($productId) {
                $this->info("Linked #{$mentor->id} {$mentor->display_name} → product #{$productId}");
                $created++;
            }
        }

        $this->info(($dryRun ? 'Would process' : 'Processed') . ": {$created}");

        return self::SUCCESS;
    }
}
