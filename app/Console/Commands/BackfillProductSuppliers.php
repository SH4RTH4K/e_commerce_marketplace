<?php

namespace App\Console\Commands;

use App\Services\Dropshipping\SupplierAssociationService;
use Illuminate\Console\Command;

class BackfillProductSuppliers extends Command
{
    protected $signature = 'suppliers:backfill-products';
    protected $description = 'Fill missing ownership from existing integration mappings and report conflicting product assignments';

    public function handle(SupplierAssociationService $associations): int
    {
        $report = $associations->backfill();
        $this->info("Updated: {$report['updated']}; unchanged: {$report['unchanged']}; conflicts: ".count($report['conflicts']));
        foreach ($report['conflicts'] as $conflict) {
            $this->warn('Product '.$conflict['product_id'].': '.$conflict['reason'].'; mapped suppliers: '.implode(', ', $conflict['mapped_supplier_ids']));
        }

        return $report['conflicts'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
