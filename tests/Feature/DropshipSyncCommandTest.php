<?php

namespace Tests\Feature;

use App\Models\DropshipSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DropshipSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_refuses_to_run_when_the_feature_flag_is_off(): void
    {
        config(['dropshipping.enabled' => false]);

        $this->artisan('dropship:sync', ['supplier' => 'missing', '--catalog' => true])
            ->assertExitCode(1);
    }

    public function test_catalog_command_dispatches_a_queue_job_when_enabled(): void
    {
        config(['dropshipping.enabled' => true]);
        Queue::fake();
        $supplier = DropshipSupplier::create([
            'key' => 'supplier',
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
            'is_active' => true,
        ]);

        $this->artisan('dropship:sync', ['supplier' => $supplier->key, '--catalog' => true])
            ->assertExitCode(0);

        Queue::assertPushed(\App\Jobs\Dropshipping\StartSupplierCatalogSync::class);
    }
}
