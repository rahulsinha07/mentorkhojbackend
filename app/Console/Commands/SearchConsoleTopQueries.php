<?php

namespace App\Console\Commands;

use App\Services\SearchConsoleService;
use Illuminate\Console\Command;

class SearchConsoleTopQueries extends Command
{
    protected $signature = 'mentorkhoj:gsc-top-queries {--days=28} {--limit=25} {--pages : Show top pages instead of queries}';

    protected $description = 'Pull real Search Console data (clicks, impressions, CTR, position) via the service account';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $endDate = now()->subDays(2)->toDateString(); // GSC data has ~2 day lag
        $startDate = now()->subDays(2 + $days)->toDateString();

        $service = new SearchConsoleService();

        if ($this->option('pages')) {
            $rows = $service->topPages($startDate, $endDate, $limit);
            $this->info("Top pages: {$startDate} to {$endDate}");
            $this->table(['Page', 'Clicks', 'Impressions', 'CTR %', 'Avg position'], collect($rows)->map(fn ($r) => [$r['page'], $r['clicks'], $r['impressions'], $r['ctr'], $r['position']]));
        } else {
            $rows = $service->topQueries($startDate, $endDate, $limit);
            $this->info("Top queries: {$startDate} to {$endDate}");
            $this->table(['Query', 'Clicks', 'Impressions', 'CTR %', 'Avg position'], collect($rows)->map(fn ($r) => [$r['query'], $r['clicks'], $r['impressions'], $r['ctr'], $r['position']]));
        }

        return self::SUCCESS;
    }
}
