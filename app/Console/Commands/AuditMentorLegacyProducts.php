<?php

namespace App\Console\Commands;

use App\Model\Mentor\Mentor;
use Illuminate\Console\Command;

class AuditMentorLegacyProducts extends Command
{
    protected $signature = 'mentors:audit-legacy-products {--published : Only published mentors}';

    protected $description = 'List mentors missing legacy_product_id (required for online session checkout)';

    public function handle(): int
    {
        $query = Mentor::query()->where(function ($q) {
            $q->whereNull('legacy_product_id')->orWhere('legacy_product_id', 0);
        });

        if ($this->option('published')) {
            $query->where('is_published', true);
        }

        $rows = $query->orderBy('id')->get(['id', 'username', 'display_name', 'legacy_product_id', 'is_published']);

        if ($rows->isEmpty()) {
            $this->info('All mentors have legacy_product_id set.');

            return self::SUCCESS;
        }

        $this->warn('Mentors missing legacy_product_id: ' . $rows->count());
        $this->table(
            ['id', 'username', 'display_name', 'published'],
            $rows->map(fn ($m) => [
                $m->id,
                $m->username,
                $m->display_name,
                $m->is_published ? 'yes' : 'no',
            ])->all()
        );

        $this->line('');
        $this->line('Fix: php artisan mentors:ensure-legacy-products --published');

        return self::SUCCESS;
    }
}
