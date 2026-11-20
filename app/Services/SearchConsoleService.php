<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\SearchConsole as GoogleSearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

class SearchConsoleService
{
    private GoogleClient $client;
    private string $siteUrl;

    public function __construct()
    {
        $this->siteUrl = config('services.search_console.site_url');

        $this->client = new GoogleClient();
        $this->client->setAuthConfig(config('services.search_console.credentials_path'));
        $this->client->addScope(GoogleSearchConsole::WEBMASTERS_READONLY);
    }

    /**
     * Query-level search performance (clicks, impressions, CTR, position).
     *
     * @return array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function topQueries(string $startDate, string $endDate, int $rowLimit = 25): array
    {
        $service = new GoogleSearchConsole($this->client);

        $request = new SearchAnalyticsQueryRequest([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['query'],
            'rowLimit' => $rowLimit,
        ]);

        $response = $service->searchanalytics->query($this->siteUrl, $request);

        return collect($response->getRows() ?? [])->map(fn ($row) => [
            'query' => $row->getKeys()[0] ?? '',
            'clicks' => (int) $row->getClicks(),
            'impressions' => (int) $row->getImpressions(),
            'ctr' => round($row->getCtr() * 100, 2),
            'position' => round($row->getPosition(), 1),
        ])->all();
    }

    /**
     * Page-level search performance (clicks, impressions, CTR, position).
     *
     * @return array<int, array{page: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function topPages(string $startDate, string $endDate, int $rowLimit = 25): array
    {
        $service = new GoogleSearchConsole($this->client);

        $request = new SearchAnalyticsQueryRequest([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['page'],
            'rowLimit' => $rowLimit,
        ]);

        $response = $service->searchanalytics->query($this->siteUrl, $request);

        return collect($response->getRows() ?? [])->map(fn ($row) => [
            'page' => $row->getKeys()[0] ?? '',
            'clicks' => (int) $row->getClicks(),
            'impressions' => (int) $row->getImpressions(),
            'ctr' => round($row->getCtr() * 100, 2),
            'position' => round($row->getPosition(), 1),
        ])->all();
    }
}
