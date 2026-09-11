<?php

namespace App\Services\Dropshipping\Suppliers;

use App\Models\DropshipSupplier;
use App\Services\Dropshipping\Contracts\SupplierClient;
use App\Services\Dropshipping\DTO\SupplierCapabilities;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Services\Dropshipping\DTO\SupplierConnectionResult;
use App\Services\Dropshipping\DTO\SupplierProduct;
use App\Services\Dropshipping\DTO\SupplierProductPage;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * Connection/configuration driver for the Mohasagor reseller API.
 *
 * The old WordPress project establishes the endpoint and header names, but it
 * does not provide a trusted live payload contract. Network connection checks
 * are therefore supported now; catalog normalization remains deliberately
 * unavailable until sanitized supplier fixtures are supplied.
 */
final class MohasagorClient implements SupplierClient
{
    public function driverKey(): string
    {
        return 'mohasagor';
    }

    public function capabilities(): SupplierCapabilities
    {
        return new SupplierCapabilities();
    }

    public function testConnection(DropshipSupplier $supplier): SupplierConnectionResult
    {
        $baseUrl = rtrim((string) $supplier->base_url, '/');
        $parts = parse_url($baseUrl);

        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'mohasagor.com.bd') {
            return new SupplierConnectionResult(false, 'Mohasagor requires an HTTPS mohasagor.com.bd API URL.');
        }

        if (trim((string) $supplier->api_key) === '' || trim((string) $supplier->secret_key) === '') {
            return new SupplierConnectionResult(false, 'API key and secret key are required.');
        }

        try {
            $response = Http::acceptJson()
                ->withOptions(['verify' => \Composer\CaBundle\CaBundle::getBundledCaBundlePath()])
                ->timeout(15)
                ->withHeaders([
                    'api-key' => trim((string) $supplier->api_key),
                    'secret-key' => trim((string) $supplier->secret_key),
                ])
                ->get($baseUrl . '/product', ['page' => 1]);
        } catch (\Throwable) {
            return new SupplierConnectionResult(false, 'Supplier connection could not be completed.');
        }

        return match (true) {
            $response->successful() => new SupplierConnectionResult(true, 'Connection succeeded.', $response->status()),
            in_array($response->status(), [401, 403], true) => new SupplierConnectionResult(false, 'Supplier credentials were rejected.', $response->status()),
            $response->status() === 429 => new SupplierConnectionResult(false, 'Supplier rate limit reached.', 429),
            $response->serverError() => new SupplierConnectionResult(false, 'Supplier service is temporarily unavailable.', $response->status()),
            default => new SupplierConnectionResult(false, 'Supplier rejected the connection request.', $response->status()),
        };
    }

    public function fetchProductsPage(
        DropshipSupplier $supplier,
        int $page,
        array $filters = [],
        bool $bypassCache = false,
    ): SupplierProductPage {
        throw new LogicException('Mohasagor catalog normalization is pending sanitized supplier fixtures.');
    }

    public function fetchProduct(DropshipSupplier $supplier, string $supplierProductId): ?SupplierProduct
    {
        throw new LogicException('Mohasagor product normalization is pending sanitized supplier fixtures.');
    }

    public function fetchCategories(DropshipSupplier $supplier, bool $bypassCache = false): array
    {
        throw new LogicException('Mohasagor category normalization is pending sanitized supplier fixtures.');
    }
}
