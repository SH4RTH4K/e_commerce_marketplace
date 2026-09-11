<?php

namespace App\Services\Dropshipping\Suppliers;

use App\Models\DropshipDriverProfile;
use App\Models\DropshipSupplier;
use App\Services\Dropshipping\Contracts\SupplierClient;
use App\Services\Dropshipping\DTO\SupplierCapabilities;
use App\Services\Dropshipping\DTO\SupplierCategory;
use App\Services\Dropshipping\DTO\SupplierConnectionResult;
use App\Services\Dropshipping\DTO\SupplierProduct;
use App\Services\Dropshipping\DTO\SupplierProductPage;
use App\Services\Dropshipping\DTO\SupplierVariant;
use App\Services\Dropshipping\Security\SupplierImageUrlValidator;
use Illuminate\Support\Facades\Http;
use LogicException;
use Throwable;

/**
 * Data-only REST driver. It executes no administrator-provided PHP; the
 * profile only describes safe relative paths and JSON field mappings.
 */
final class ConfigurableRestClient implements SupplierClient
{
    public function __construct(private readonly string $profileKey, private readonly ?SupplierImageUrlValidator $urlValidator = null)
    {
    }

    public function driverKey(): string
    {
        return $this->profileKey;
    }

    public function capabilities(): SupplierCapabilities
    {
        $profile = $this->profile();
        $mapping = $profile->field_mapping ?? [];
        $fields = is_array($mapping['fields'] ?? null) ? $mapping['fields'] : [];

        return new SupplierCapabilities(
            categories: is_string($profile->categories_path) && $profile->categories_path !== '',
            productDetail: isset($fields['id'], $fields['name']),
            variants: isset($mapping['variant_collection']),
            stock: isset($fields['stock_quantity']),
            images: isset($fields['image_urls']),
        );
    }

    public function testConnection(DropshipSupplier $supplier): SupplierConnectionResult
    {
        try {
            $profile = $this->profile();
            $response = $this->request($supplier, $profile->products_path, [(string) $profile->pagination_param => 1]);
        } catch (\InvalidArgumentException $exception) {
            return new SupplierConnectionResult(false, 'API configuration error: ' . $exception->getMessage());
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            report($exception);
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'certificate') || str_contains($message, 'ssl')) {
                return new SupplierConnectionResult(false, 'SSL certificate error while connecting to the supplier.');
            }
            if (str_contains($message, 'could not resolve') || str_contains($message, 'name or service not known')) {
                return new SupplierConnectionResult(false, 'DNS error: the supplier host could not be resolved.');
            }
            return new SupplierConnectionResult(false, 'Network error: the supplier endpoint could not be reached.');
        } catch (Throwable $exception) {
            report($exception);
            return new SupplierConnectionResult(false, 'Driver request error: verify the saved API profile and endpoint path.');
        }

        return match (true) {
            $response->successful() => new SupplierConnectionResult(true, 'Connection succeeded.', $response->status()),
            in_array($response->status(), [401, 403], true) => new SupplierConnectionResult(false, 'Supplier credentials were rejected.', $response->status()),
            $response->status() === 429 => new SupplierConnectionResult(false, 'Supplier rate limit reached.', 429),
            $response->serverError() => new SupplierConnectionResult(false, 'Supplier service is temporarily unavailable.', $response->status()),
            default => new SupplierConnectionResult(false, 'Supplier rejected the connection request.', $response->status()),
        };
    }

    public function fetchProductsPage(DropshipSupplier $supplier, int $page, array $filters = [], bool $bypassCache = false): SupplierProductPage
    {
        $profile = $this->profile();
        $mapping = $profile->field_mapping ?? [];
        $fields = is_array($mapping['fields'] ?? null) ? $mapping['fields'] : [];
        if (! isset($fields['id'], $fields['name'])) {
            throw new LogicException('This driver profile needs id and name field mappings before catalog sync can run.');
        }

        $response = $this->request($supplier, $profile->products_path, array_merge($filters, [(string) $profile->pagination_param => $page]));
        if (! $response->successful()) {
            throw new LogicException('Supplier catalog request failed with status ' . $response->status() . '.');
        }
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new LogicException('Supplier catalog response must be a JSON object or array.');
        }
        $collectionPath = $profile->collection_path ?: (is_string($mapping['collection_path'] ?? null) ? $mapping['collection_path'] : '');
        $items = $this->valueAt($payload, $collectionPath);
        if ($items === null) {
            $items = array_is_list($payload) ? $payload : [];
        }
        if (! is_array($items)) {
            throw new LogicException('Configured product collection path did not contain an array.');
        }

        $products = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $products[] = $this->productFromPayload($item, $fields, $mapping, $profile->default_currency, $supplier->price_field_mapping ?? []);
        }
        $current = $this->integerAt($payload, $mapping['pagination']['current_page'] ?? null) ?? $page;
        $last = $this->integerAt($payload, $mapping['pagination']['last_page'] ?? null);
        $next = $this->integerAt($payload, $mapping['pagination']['next_page'] ?? null);
        $pageSize = $this->integerAt($payload, $mapping['pagination']['page_size'] ?? null)
            ?? (is_numeric($mapping['pagination']['page_size'] ?? null) ? (int) $mapping['pagination']['page_size'] : 200);
        if ($last === null && $next === null && count($products) >= $pageSize) {
            $next = $page + 1;
        }

        return new SupplierProductPage($products, $current, $last, $next);
    }

    public function fetchProduct(DropshipSupplier $supplier, string $supplierProductId): ?SupplierProduct
    {
        throw new LogicException('This data-only driver does not define a product-detail endpoint. Use catalog mappings.');
    }

    public function fetchCategories(DropshipSupplier $supplier, bool $bypassCache = false): array
    {
        $profile = $this->profile();
        if (! $profile->categories_path) {
            return [];
        }
        $response = $this->request($supplier, $profile->categories_path, []);
        if (! $response->successful()) {
            throw new LogicException('Supplier category request failed with status ' . $response->status() . '.');
        }
        $payload = $response->json();
        $profileMapping = is_array($profile->field_mapping) ? $profile->field_mapping : [];
        $items = is_array($payload) ? ($this->valueAt($payload, (string) ($profileMapping['category_collection'] ?? '')) ?? $payload) : [];
        $mapping = is_array($profileMapping['category_fields'] ?? null) ? $profileMapping['category_fields'] : [];
        $result = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) continue;
            $id = $this->scalar($this->valueAt($item, $mapping['id'] ?? 'id'));
            $name = $this->scalar($this->valueAt($item, $mapping['name'] ?? 'name'));
            if ($id !== null && $name !== null) $result[] = new SupplierCategory($id, $name, null, null, null, $item);
        }
        return $result;
    }

    private function productFromPayload(array $item, array $fields, array $mapping, string $currency, array $supplierPriceMapping = []): SupplierProduct
    {
        // Supplier-specific mapping is applied at the adapter boundary. The
        // rest of the application receives only normalized costPrice/maxPrice.
        if (is_string($supplierPriceMapping['cost_field'] ?? null) && $supplierPriceMapping['cost_field'] !== '') {
            $fields['cost_price'] = $supplierPriceMapping['cost_field'];
        }
        if (is_string($supplierPriceMapping['maximum_field'] ?? null) && $supplierPriceMapping['maximum_field'] !== '') {
            $fields['max_price'] = $supplierPriceMapping['maximum_field'];
        }
        $get = fn (string $name): mixed => $this->valueAt($item, $fields[$name] ?? '');
        $images = $this->list($get('image_urls'));
        $variants = [];
        $variantItems = $this->arrayList($this->valueAt($item, (string) ($mapping['variant_collection'] ?? '')));
        $variantFields = is_array($mapping['variant_fields'] ?? null) ? $mapping['variant_fields'] : [];
        foreach ($variantItems as $variant) {
            if (! is_array($variant)) continue;
            $v = fn (string $name): mixed => $this->valueAt($variant, $variantFields[$name] ?? '');
            $id = $this->scalar($v('id'));
            if ($id === null) continue;
            $attributes = is_array($variantFields['attributes'] ?? null)
                ? $this->mappedVariantAttributes($variant, $variantFields['attributes'])
                : (is_array($v('attributes')) ? $v('attributes') : []);
            $variants[] = new SupplierVariant($id, $this->scalar($v('sku')), $attributes, $this->number($v('cost_price')), $this->number($v('max_price')), $this->number($v('stock_quantity')), $this->boolean($v('is_available')), $this->scalar($v('currency')), $variant);
        }

        $id = $this->scalar($get('id'));
        $name = $this->scalar($get('name'));
        if ($id === null || $name === null) throw new LogicException('A mapped product is missing id or name.');
        return new SupplierProduct($id, $name, strtoupper($this->scalar($get('currency')) ?: $currency), $this->scalar($get('product_code')), $this->scalar($get('category_key')), $this->number($get('cost_price')), $this->number($get('max_price')), $this->number($get('stock_quantity')), $this->boolean($get('is_available')), $this->scalar($get('status')), $this->scalar($get('brand')), $this->scalar($get('description')), $this->scalar($get('unit')), $images, $variants, $item);
    }

    private function profile(): DropshipDriverProfile
    {
        return DropshipDriverProfile::query()->where('key', $this->profileKey)->where('is_active', true)->firstOrFail();
    }

    private function request(DropshipSupplier $supplier, string $path, array $query)
    {
        $base = rtrim((string) $supplier->base_url, '/');
        $parts = parse_url($base);
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $validator = $this->urlValidator ?? new SupplierImageUrlValidator();
        $validator->validate($base, [$host]);
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..') || preg_match('/[?#]/', $path)) {
            throw new LogicException('Driver endpoint paths must be relative and cannot contain traversal or query syntax.');
        }
        $headers = [];
        if (trim((string) $supplier->api_key) !== '') $headers[$this->profile()->auth_key_header] = (string) $supplier->api_key;
        if (trim((string) $supplier->secret_key) !== '') $headers[$this->profile()->auth_secret_header] = (string) $supplier->secret_key;
        return Http::acceptJson()
            // Windows PHP installations often have no curl.cainfo configured.
            // Use Composer's maintained CA bundle without disabling TLS
            // verification, so supplier HTTPS certificates are still checked.
            ->withOptions(['verify' => \Composer\CaBundle\CaBundle::getBundledCaBundlePath()])
            ->timeout(20)
            ->withHeaders($headers)
            ->get($base . '/' . ltrim($path, '/'), $query);
    }

    private function valueAt(mixed $value, string $path): mixed
    {
        if ($path === '') return $value;
        foreach (explode('.', trim($path, '.')) as $segment) {
            if ($segment === '') continue;
            if (! is_array($value)) return null;
            if (str_ends_with($segment, '[]')) {
                $key = substr($segment, 0, -2);
                $value = $value[$key] ?? null;
                if (! is_array($value)) return null;
                $out = [];
                foreach ($value as $entry) $out[] = $entry;
                $value = $out;
            } else {
                $value = $value[$segment] ?? null;
            }
        }
        return $value;
    }

    private function scalar(mixed $value): ?string { return is_scalar($value) ? trim((string) $value) : null; }
    private function number(mixed $value): ?float { return is_numeric($value) ? (float) $value : null; }
    private function boolean(mixed $value): ?bool { return is_bool($value) ? $value : (is_numeric($value) ? (bool) $value : null); }
    private function list(mixed $value): array { return is_array($value) ? array_values(array_filter($value, fn ($v) => is_scalar($v) && trim((string) $v) !== '')) : []; }
    private function arrayList(mixed $value): array { return is_array($value) ? array_values(array_filter($value, 'is_array')) : []; }
    private function mappedVariantAttributes(array $variant, array $mapping): array
    {
        $attributes = [];
        foreach ($mapping as $key => $path) {
            if (! is_string($path)) continue;
            $value = $this->scalar($this->valueAt($variant, $path));
            if ($value !== null && $value !== '') $attributes[(string) $key] = $value;
        }

        return $attributes;
    }
    private function integerAt(array $payload, mixed $path): ?int { $v = is_string($path) ? $this->valueAt($payload, $path) : null; return is_numeric($v) ? (int) $v : null; }
}
