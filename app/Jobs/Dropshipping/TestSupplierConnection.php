<?php

namespace App\Jobs\Dropshipping;

use App\Models\DropshipSupplier;
use App\Services\Dropshipping\SupplierRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TestSupplierConnection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $supplierId)
    {
    }

    public function handle(SupplierRegistry $registry): void
    {
        $supplier = DropshipSupplier::query()->findOrFail($this->supplierId);
        $client = $registry->forDriver($supplier->driver_key);
        $result = $client->testConnection($supplier);
        $now = now();
        $message = $this->safeMessage($result->message, $result->successful);

        $supplier->forceFill([
            'last_connection_status' => $result->successful ? 'success' : 'failed',
            // Persist only an allow-listed, driver-generated explanation;
            // never persist arbitrary supplier response bodies.
            'last_connection_message' => $message,
            'last_connection_tested_at' => $now,
            'last_connection_success_at' => $result->successful ? $now : $supplier->last_connection_success_at,
            'capabilities' => $client->capabilities()->toArray(),
        ])->save();
    }

    private function safeMessage(string $message, bool $successful): string
    {
        $allowed = [
            'Connection succeeded.',
            'Supplier credentials were rejected.',
            'Supplier rate limit reached.',
            'Supplier service is temporarily unavailable.',
            'Supplier rejected the connection request.',
            'Supplier connection could not be completed safely.',
            'Supplier connection could not be completed.',
            'API key and secret key are required.',
            'Mohasagor requires an HTTPS mohasagor.com.bd API URL.',
        ];

        if (in_array($message, $allowed, true)) {
            return $message;
        }

        return $successful ? 'Connection succeeded.' : 'Supplier connection failed.';
    }

    public function failed(Throwable $exception): void
    {
        $supplier = DropshipSupplier::query()->find($this->supplierId);
        if ($supplier === null) {
            return;
        }

        $supplier->forceFill([
            'last_connection_status' => 'failed',
            'last_connection_message' => 'Supplier connection check could not be completed.',
            'last_connection_tested_at' => now(),
        ])->save();
    }
}
