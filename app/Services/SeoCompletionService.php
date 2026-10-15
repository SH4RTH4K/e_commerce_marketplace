<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SeoCompletionService
{
    public const FIELDS = ['meta_title', 'meta_description', 'meta_keywords'];

    private const CATEGORY_COPY = [
        'mens-fashion' => "Shop men's fashion at TAQI LIFE in Bangladesh. Explore clothing and accessories, compare product details and choose styles for everyday wear.",
        'womens-fashion' => "Shop women's fashion at TAQI LIFE in Bangladesh. Explore clothing and accessories, compare product details and find styles for your wardrobe.",
        'kids-zone' => 'Explore the Kids Zone at TAQI LIFE in Bangladesh. Browse products for children and compare sizes, features and product details before choosing.',
        'gadgets-electronics' => 'Shop gadgets and electronics at TAQI LIFE in Bangladesh. Compare specifications and features to find devices and accessories for your needs.',
        'watch' => 'Browse watches at TAQI LIFE in Bangladesh. Explore available styles and compare designs, features and product specifications before choosing.',
        'home-lifestyle' => 'Explore Home & Lifestyle at TAQI LIFE in Bangladesh. Browse products for your home and daily routine, and compare features and product details.',
        'foods' => 'Explore the Foods collection at TAQI LIFE in Bangladesh. Check product descriptions, ingredients and available listings before placing your order.',
        'customize-and-gift' => 'Explore Customize & Gift at TAQI LIFE in Bangladesh. Browse gift ideas and check each product for designs, options and customization details.',
        'winter' => 'Browse the Winter collection at TAQI LIFE in Bangladesh. Compare product details, materials and sizes to choose items for the cooler season.',
        'others' => 'Explore more products at TAQI LIFE in Bangladesh. Browse the collection and compare features, specifications and product details before choosing.',
        'supplier-category-offer' => 'Explore the offer collection at TAQI LIFE in Bangladesh. Review individual product listings, specifications and current prices before ordering.',
        'hoco' => 'Explore the HOCO collection at TAQI LIFE in Bangladesh. Check the latest product listings and compare model specifications before ordering.',
    ];

    public function summary(): array
    {
        $result = [];
        foreach (['categories' => Category::class, 'products' => Product::class] as $type => $model) {
            $select = ['COUNT(*) AS total'];
            foreach (self::FIELDS as $field) {
                $select[] = "SUM(CASE WHEN TRIM(COALESCE({$field}, '')) = '' THEN 1 ELSE 0 END) AS {$field}";
            }
            $row = $model::query()->selectRaw(implode(', ', $select))->first();
            $result[$type] = ['total' => (int) $row->total, 'incomplete' => $this->pending($model)->count()];
            foreach (self::FIELDS as $field) {
                $result[$type][$field] = (int) $row->{$field};
            }
        }

        return $result;
    }

    public function preview(): array
    {
        $samples = [];
        foreach (['categories' => Category::class, 'products' => Product::class] as $type => $model) {
            foreach ($this->pending($model)->orderByDesc('id')->limit(12)->get() as $record) {
                $samples[] = ['type' => $type, 'id' => $record->id, 'name' => $record->name,
                    'current' => $record->only(self::FIELDS), 'proposed' => $this->proposed($record),
                    'edit_url' => '/admin/'.$type.'/'.$record->id.'/edit'];
            }
        }

        return $samples;
    }

    public function proposed(Category|Product $record): array
    {
        $name = $this->plain($record->name);
        $store = $this->plain(site_name()) ?: 'TAQI LIFE';
        $store = $this->clip($store, 24);
        $titleBudget = max(20, 65 - mb_strlen($store) - 3);
        $core = $name;
        if ($record instanceof Product) {
            $firstClause = preg_split('/\s+[–—|]\s+/', $name)[0];
            if (mb_strlen($firstClause) >= 15) {
                $core = $firstClause;
            }
            $sku = $this->clip($this->plain($record->sku), 12);
            if (mb_strlen($name) > $titleBudget && $sku !== '' && ! str_contains(mb_strtolower($core), mb_strtolower($sku))) {
                $core = $this->clip($core, $titleBudget - mb_strlen($sku) - 3).' ('.$sku.')';
            } else {
                $core = $this->clip($core, $titleBudget);
            }
        } else {
            $core = $this->clip($core, $titleBudget);
        }
        $title = $core.' | '.$store;
        $ending = ' at '.$store.' in Bangladesh.';
        $description = 'Shop '.$this->clip($name, 160 - mb_strlen($ending) - 5).$ending;
        if ($record instanceof Category && isset(self::CATEGORY_COPY[$record->slug])) {
            $description = str_replace('TAQI LIFE', $store, self::CATEGORY_COPY[$record->slug]);
        }
        $keywordTitle = $this->plain($record->meta_title) ?: $title;
        $keywordCore = trim(explode('|', $keywordTitle)[0]);
        if ($record instanceof Product && isset($sku) && $sku !== '') {
            $keywordCore = preg_replace('/\s+\('.preg_quote($sku, '/').'\)$/u', '', $keywordCore) ?? $keywordCore;
        }
        $keywords = implode(', ', array_unique(array_filter([$keywordCore, $store])));
        $generated = ['meta_title' => $title, 'meta_description' => $this->clip($description, 160), 'meta_keywords' => $this->clip($keywords, 500)];

        return array_filter($generated, fn ($value, $field) => trim((string) $record->{$field}) === '', ARRAY_FILTER_USE_BOTH);
    }

    /** Update only blank metadata, with a private before/after snapshot and concurrency checks. */
    public function complete(int $actorId): array
    {
        $lock = Cache::lock('catalog-seo-completion', 300);
        if (! $lock->get()) {
            throw new RuntimeException('SEO completion is already running. Refresh this page to check the results.');
        }
        try {
            $changes = [];
            foreach (['categories' => Category::class, 'products' => Product::class] as $type => $model) {
                $this->pending($model)->orderBy('id')->chunkById(200, function ($records) use (&$changes, $type) {
                    foreach ($records as $record) {
                        $patch = $this->proposed($record);
                        if ($patch !== []) {
                            $changes[] = ['type' => $type, 'id' => $record->id, 'name' => $record->name,
                                'before' => $record->only(array_keys($patch)), 'after' => $patch];
                        }
                    }
                });
            }
            $runId = (string) Str::uuid();
            $audit = ['run_id' => $runId, 'actor_id' => $actorId, 'created_at' => now()->toIso8601String(), 'status' => 'planned', 'changes' => $changes];
            $path = 'seo-completion/'.$runId.'.json';
            if (! Storage::disk('local')->put($path, json_encode($audit, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) {
                throw new RuntimeException('Could not save the private SEO snapshot. No metadata was changed.');
            }
            $result = ['categories' => 0, 'products' => 0, 'fields' => 0, 'skipped' => 0, 'run_id' => $runId];
            foreach (array_chunk($changes, 200) as $batch) {
                DB::transaction(function () use ($batch, &$result) {
                    foreach ($batch as $change) {
                        $model = $change['type'] === 'categories' ? Category::class : Product::class;
                        $query = $model::query()->whereKey($change['id'])->where('name', $change['name']);
                        foreach ($change['before'] as $field => $value) {
                            $query->where($field, $value);
                        }
                        if ($query->update($change['after'])) {
                            $result[$change['type']]++;
                            $result['fields'] += count($change['after']);
                        } else {
                            $result['skipped']++;
                        }
                    }
                });
            }
            $audit['status'] = 'completed';
            $audit['result'] = $result;
            Storage::disk('local')->put($path, json_encode($audit, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            Cache::forget('sitemap_urls');

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function pending(string $model): Builder
    {
        return $model::query()->where(function ($query) {
            foreach (self::FIELDS as $field) {
                $query->orWhereRaw("TRIM(COALESCE({$field}, '')) = ''");
            }
        });
    }

    private function plain(?string $value): string
    {
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $value);
        $value = preg_replace('/<[^>]+>/', ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function clip(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        $part = mb_substr($value, 0, $limit + 1);
        $space = mb_strrpos($part, ' ');

        return preg_replace('/[\s,;:\-–—]+$/u', '', $space !== false && $space > $limit / 2 ? mb_substr($part, 0, $space) : mb_substr($part, 0, $limit)) ?? '';
    }
}
