<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductCatalogService
{
    public function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'category' => ['nullable', 'integer', 'min:1'],
            'supplier' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'string'],
            'stock' => ['nullable', 'string'], 'stock_level' => ['nullable', 'string'],
            'type' => ['nullable', 'string'], 'sort' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer'],
        ]);
        $term = preg_replace('/\s+/u', ' ', trim($data['q'] ?? '')) ?? trim($data['q'] ?? '');
        $status = match ($data['status'] ?? '') { 'active' => 'published', 'inactive' => 'draft', default => $data['status'] ?? '' };
        $stock = $data['stock'] ?? $data['stock_level'] ?? '';
        return [
            'q' => $term, 'category' => isset($data['category']) ? (int) $data['category'] : '',
            'supplier' => isset($data['supplier']) ? (int) $data['supplier'] : '',
            'status' => in_array($status, ['published', 'draft'], true) ? $status : '',
            'stock' => in_array($stock, ['out', 'low', 'in'], true) ? $stock : '',
            'type' => in_array($data['type'] ?? '', ['simple', 'variable'], true) ? $data['type'] : '',
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
                    ->orWhereRaw("sku LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("brand LIKE ? ESCAPE '!'", [$pattern]));
            }
        }
        if ($filters['category'] !== '') {
            $category = Category::find($filters['category']);
            $query->whereIn('category_id', $category?->selfAndDescendantIds() ?? []);
        }
        if ($filters['supplier'] !== '') $query->whereHas('supplierLinks.supplierProduct', fn ($query) => $query->where('supplier_id', $filters['supplier']));
        if ($filters['type'] === 'variable') $query->has('variants');
        if ($filters['type'] === 'simple') $query->doesntHave('variants');
        match ($filters['stock']) {
            'out' => $query->where('stock_quantity', 0), 'low' => $query->whereBetween('stock_quantity', [1, 5]),
            'in' => $query->where('stock_quantity', '>', 5), default => null,
        };
        return $query;
    }

    public function visibility(Builder $query, string $status): Builder
    {
        if ($status !== '') $query->where('is_published', $status === 'published');
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
        return array_filter($filters, fn ($value) => $value !== '' && $value !== null);
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
