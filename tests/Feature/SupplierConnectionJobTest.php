<?php

namespace Tests\Feature;

use App\Jobs\Dropshipping\TestSupplierConnection;
use App\Models\DropshipSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupplierConnectionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_connection_checks_are_dispatched_to_the_queue(): void
    {
        Queue::fake();
        $supplier = DropshipSupplier::create([
            'key' => 'supplier',
            'name' => 'Supplier',
            'driver_key' => 'fake',
            'base_url' => 'https://supplier.example',
        ]);

        TestSupplierConnection::dispatch($supplier->id);

        Queue::assertPushed(TestSupplierConnection::class, fn ($job) => $job->supplierId === $supplier->id);
    }
}
