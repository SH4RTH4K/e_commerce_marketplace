<?php

namespace App\Services;

use App\Http\Requests\IndexProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductCatalogService
{
    public function filters(IndexProductRequest $request): array
    {
        $data = $request->validated();
        $term = preg_replace('/\s+/u', ' ', trim($data['q'] ?? '')) ?? trim($data['q'] ?? '');
        $status = match ($data['status'] ?? '') {
            'active' => 'published', 'inactive' => 'draft', default => $data['status'] ?? ''
        };
        $stock = $data['stock'] ?? $data['stock_level'] ?? '';

        return [
            'q' => $term, 'category' => isset($data['category']) ? (int) $data['category'] : '',
            'category_scope' => isset($data['category']) && ($data['category_scope'] ?? '') === 'direct' ? 'direct' : 'all',
            'exclude_category' => isset($data['exclude_category']) ? (int) $data['exclude_category'] : '',
            'supplier' => isset($data['supplier']) ? (int) $data['supplier'] : '',
            'status' => in_array($status, ['published', 'draft'], true) ? $status : '',
            'stock' => in_array($stock, ['out', 'low', 'in'], true) ? $stock : '',
            'type' => in_array($data['type'] ?? '', ['simple', 'variable'], true) ? $data['type'] : '',
            'source' => in_array($data['source'] ?? '', ['manual', 'dropshipping', 'other'], true) ? $data['source'] : '',
            'sort' => in_array($data['sort'] ?? '', ['relevance', 'newest', 'updated', 'name', 'price_asc', 'price_desc', 'stock_asc', 'stock_desc'], true) ? $data['sort'] : ($term !== '' ? 'relevance' : 'newest'),
            'per_page' => in_array((int) ($data['per_page'] ?? 15), [15, 30, 50, 100], true) ? (int) ($data['per_page'] ?? 15) : 15,
        ];
    }

    /** Treat wildcard characters as literal search text, with all words required. */
    public function query(array $filters): Builder
    {
        $query = Product::query();
        if ($filters['q'] !== '') {
            foreach (preg_split('/\s+/u', $filters['q'], 20) ?: [$filters['q']] as $word) {
                $pattern = '%'.$this->escapeLike($word).'%';
                $query->where(fn ($match) => $match->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("sku LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("brand LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("supplier_code LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('supplierLinks.supplierProduct', fn ($source) => $source
                        ->whereRaw("product_code LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("supplier_product_id LIKE ? ESCAPE '!'", [$pattern]))
                );
            }
        }
        if ($filters['category'] !== '') {
            if (($filters['category_scope'] ?? 'all') === 'direct') {
                $query->where('category_id', $filters['category']);
            } else {
                $category = Category::find($filters['category']);
                $query->whereIn('category_id', $category?->selfAndDescendantIds() ?? []);
            }
        }
        if (($filters['exclude_category'] ?? '') !== '') {
            $excludedCategory = Category::find($filters['exclude_category']);
            $excludedIds = $excludedCategory?->selfAndDescendantIds() ?? [(int) $filters['exclude_category']];
            $query->where(fn (Builder $categoryQuery) => $categoryQuery
                ->whereNull('category_id')
                ->orWhereNotIn('category_id', $excludedIds));
        }
        if ($filters['supplier'] !== '') {
            $query->where(fn ($supplierQuery) => $supplierQuery
                ->where('supplier_id', $filters['supplier'])
                ->orWhere(fn ($legacyQuery) => $legacyQuery->whereNull('supplier_id')->whereHas('supplierLinks.supplierProduct', fn ($linkQuery) => $linkQuery->where('supplier_id', $filters['supplier'])))
            );
        }
        if ($filters['type'] === 'variable') {
            $query->has('variants');
        }
        if ($filters['type'] === 'simple') {
            $query->doesntHave('variants');
        }
        if ($filters['source'] !== '') {
            $query->where('source_type', $filters['source']);
        }
        match ($filters['stock']) {
            'out' => $query->where('stock_quantity', 0), 'low' => $query->whereBetween('stock_quantity', [1, 5]),
            'in' => $query->where('stock_quantity', '>', 5), default => null,
        };

        return $query;
    }

    public function visibility(Builder $query, string $status): Builder
    {
        if ($status !== '') {
            $query->where('is_published', $status === 'published');
        }

        return $query;
    }

    public function sort(Builder $query, array $filters): Builder
    {
        match ($filters['sort']) {
            'updated' => $query->latest('updated_at'), 'name' => $query->orderBy('name'),
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, regular_price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, regular_price) DESC'),
            'stock_asc' => $query->orderBy('stock_quantity'), 'stock_desc' => $query->orderByDesc('stock_quantity'),
            'relevance' => $filters['q'] !== '' ? $query->orderByRaw("CASE WHEN LOWER(sku) = ? THEN 0 WHEN LOWER(name) = ? THEN 1 WHEN LOWER(sku) LIKE ? ESCAPE '!' THEN 2 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 3 ELSE 4 END", [mb_strtolower($filters['q']), mb_strtolower($filters['q']), $this->escapeLike(mb_strtolower($filters['q'])).'%', $this->escapeLike(mb_strtolower($filters['q'])).'%']) : $query->latest('created_at'),
            default => $query->latest('created_at'),
        };

        return $query->orderByDesc('id');
    }

    public function parameters(array $filters): array
    {
        return array_filter($filters, fn ($value, $key) => $value !== '' && $value !== null && ! ($key === 'category_scope' && $value === 'all'), ARRAY_FILTER_USE_BOTH);
    }

    public static function returnUrl(mixed $url): string
    {
        return is_string($url) && preg_match('/^\/admin\/products(?:\?[^#\x00-\x1f\x7f]*)?$/', $url) ? $url : '/admin/products';
    }

    private function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }
}
