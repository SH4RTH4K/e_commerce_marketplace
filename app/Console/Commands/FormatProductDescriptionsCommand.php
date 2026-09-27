<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Dropshipping\SupplierDescriptionFormatter;
use Illuminate\Console\Command;

class FormatProductDescriptionsCommand extends Command
{
    protected $signature = 'products:format-descriptions {--dry-run : Report changes without saving them}';

    protected $description = 'Normalize saved product descriptions into safe, readable HTML';

    public function handle(SupplierDescriptionFormatter $formatter): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $updated = 0;

        Product::query()
            ->whereNotNull('description')
            ->where('description', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($formatter, $dryRun, &$checked, &$updated): void {
                foreach ($products as $product) {
                    $checked++;
                    $description = $formatter->formatDescription($product->description);

                    if ($description === null || $description === $product->description) {
                        continue;
                    }

                    $updated++;
                    if (! $dryRun) {
                        $product->forceFill(['description' => $description])->saveQuietly();
                    }
                }
            });

        $action = $dryRun ? 'would be updated' : 'updated';
        $this->info("Checked {$checked} product descriptions; {$updated} {$action}.");

        return self::SUCCESS;
    }
}
