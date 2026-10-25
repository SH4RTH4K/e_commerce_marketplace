<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class RebuildSitemap extends Command
{
    protected $signature = 'sitemap:rebuild';

    protected $description = 'Invalidate and warm the public sitemap index and child sitemaps';

    public function handle(SitemapService $sitemaps): int
    {
        $counts = $sitemaps->rebuild();

        foreach ($counts as $type => $count) {
            $this->line(sprintf('%-12s %d URLs', ucfirst($type), $count));
        }

        $this->info('Sitemap rebuilt successfully.');

        return self::SUCCESS;
    }
}
