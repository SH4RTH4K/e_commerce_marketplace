<?php

namespace Tests\Unit\Dropshipping;

use App\Models\DropshipSupplier;
use App\Services\Dropshipping\Suppliers\MohasagorClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MohasagorClientTest extends TestCase
{
    public function test_connection_uses_the_verified_endpoint_and_header_names(): void
    {
        Http::fake([
            'https://mohasagor.com.bd/api/reseller/product*' => Http::response(['ok' => true], 200),
        ]);

        $supplier = new DropshipSupplier([
            'base_url' => 'https://mohasagor.com.bd/api/reseller',
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
        ]);

        $result = (new MohasagorClient())->testConnection($supplier);

        $this->assertTrue($result->successful);
        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-api-key')
            && $request->hasHeader('secret-key', 'test-secret-key')
            && $request->url() === 'https://mohasagor.com.bd/api/reseller/product?page=1');
    }

    public function test_auth_failure_is_sanitized(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'secret response details'], 401),
        ]);

        $supplier = new DropshipSupplier([
            'base_url' => 'https://mohasagor.com.bd/api/reseller',
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
        ]);

        $result = (new MohasagorClient())->testConnection($supplier);

        $this->assertFalse($result->successful);
        $this->assertSame('Supplier credentials were rejected.', $result->message);
        $this->assertSame(401, $result->statusCode);
    }
}
