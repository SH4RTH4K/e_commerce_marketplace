<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SkuConfigurationService
{
    public const COMPONENTS = ['static_prefix', 'category', 'subcategory', 'subsubcategory', 'sequence', 'local_id', 'supplier_code', 'supplier_product_id', 'supplier_sku', 'date', 'suffix'];

    public function config(): array
    {
        // Read the database directly: workers must observe newly saved formats.
        $saved = json_decode((string) DB::table('settings')->where('key', 'sku_configuration')->value('value'), true) ?: [];
        if (!isset($saved['components'])) {
            $saved['components'] = array_keys(array_filter([
                'static_prefix' => !empty($saved['static_prefix']), 'category' => $saved['use_category'] ?? false,
                'subcategory' => $saved['use_subcategory'] ?? false, 'subsubcategory' => $saved['use_subsubcategory'] ?? false,
                'sequence' => $saved['use_sequence'] ?? true,
            ]));
        }
        return array_intersect_key($saved + [
            'enabled' => false, 'components' => ['category', 'sequence'], 'static_prefix' => '', 'suffix' => '',
            'separator' => '-', 'case' => 'upper', 'sequence_start' => 1, 'sequence_padding' => 6, 'category_codes' => [],
        ], array_flip(['enabled', 'components', 'static_prefix', 'suffix', 'separator', 'case', 'sequence_start', 'sequence_padding', 'category_codes']));
    }

    public function validate(array $input): array
    {
        $input['separator'] = $input['separator'] ?? '';
        $input['static_prefix'] = $input['static_prefix'] ?? '';
        $input['suffix'] = $input['suffix'] ?? '';
        if (is_array($input['category_codes'] ?? [])) {
            $input['category_codes'] = array_filter($input['category_codes'] ?? [], fn ($code) => $code !== null && $code !== '');
        }
        $data = Validator::make($input, [
            'enabled' => ['required', 'boolean'], 'components' => ['required', 'array', 'min:1', 'max:11'],
            'components.*' => ['required', 'distinct', Rule::in(self::COMPONENTS)],
            'static_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]*$/'],
            'suffix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9]*$/'],
            'separator' => ['nullable', Rule::in(['-', '_', ''])], 'case' => ['required', Rule::in(['upper', 'lower'])],
            'sequence_start' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'sequence_padding' => ['required', 'integer', 'min:1', 'max:12'], 'category_codes' => ['array'],
            'category_codes.*' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/'],
        ])->validate();
        if (!array_intersect($data['components'], ['sequence', 'local_id', 'supplier_product_id', 'supplier_sku'])) {
            throw ValidationException::withMessages(['components' => 'Include a sequence, local product ID, supplier ID, or supplier SKU to identify each product.']);
        }
        foreach (['static_prefix', 'suffix'] as $field) {
            if (in_array($field, $data['components'], true) && trim((string) $data[$field]) === '') {
                throw ValidationException::withMessages([$field => 'Enter a value or remove this component.']);
            }
        }
        $validIds = Category::whereIn('id', array_keys($data['category_codes']))->pluck('id')->map(fn ($id) => (string) $id)->all();
        // Deleted categories must not leave invisible codes that prevent saving.
        $data['category_codes'] = array_intersect_key($data['category_codes'], array_flip($validIds));
        $data['enabled'] = (bool) $data['enabled'];
        $data['sequence_start'] = (int) $data['sequence_start'];
        $data['sequence_padding'] = (int) $data['sequence_padding'];
        return $data;
    }

    public function nextSequence(array $config): int
    {
        return max((int) $config['sequence_start'], (int) DB::table('settings')->where('key', 'sku_next_sequence')->value('value'));
    }

    private function lock(): void
    {
        DB::table('settings')->where('key', 'sku_configuration')->lockForUpdate()->first();
        DB::table('settings')->where('key', 'sku_next_sequence')->lockForUpdate()->first();
    }

    private function persist(array $config, ?int $next = null): void
    {
        DB::table('settings')->where('key', 'sku_configuration')->update(['value' => json_encode($config), 'updated_at' => now()]);
        if ($next !== null) DB::table('settings')->where('key', 'sku_next_sequence')->update(['value' => (string) $next, 'updated_at' => now()]);
        Setting::forgetCache();
    }

    private function audit(User $actor, string $action, string $scope, array $before, array $after, array $changes = []): int
    {
        $id = DB::table('sku_configuration_runs')->insertGetId([
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'scope' => $scope,
            'changed_count' => count($changes), 'config_before' => json_encode($before), 'config_after' => json_encode($after), 'created_at' => now(),
        ]);
        foreach (array_chunk($changes, 500) as $chunk) {
            DB::table('sku_change_items')->insert(array_map(fn ($row) => ['run_id' => $id, 'product_id' => $row['id'], 'old_sku' => $row['old_sku'], 'new_sku' => $row['new_sku']], $chunk));
        }
        return $id;
    }

    public function save(array $config, User $actor): void
    {
        DB::transaction(function () use ($config, $actor) {
            $this->lock();
            $before = $this->config();
            $this->persist($config, $this->nextSequence($config));
            $this->audit($actor, 'configuration_saved', 'future', $before, $config);
        });
    }

    public function categories(): array
    {
        $rows = Category::orderBy('name')->get(['id', 'name', 'parent_id'])->keyBy('id');
        return $rows->map(function ($category) use ($rows) {
            $path = [$category->name]; $parent = $category->parent_id; $seen = [$category->id];
            while ($parent && isset($rows[$parent]) && !in_array($parent, $seen)) {
                $seen[] = $parent; array_unshift($path, $rows[$parent]->name); $parent = $rows[$parent]->parent_id;
            }
            return ['id' => $category->id, 'name' => $category->name, 'parent_id' => $category->parent_id, 'depth' => count($path) - 1, 'path' => implode(' / ', $path)];
        })->sortBy('path')->values()->all();
    }

    private function code(Product $product, array $config, int $sequence, array $categories): string
    {
        $source = $product->supplierLinks->first()?->supplierProduct;
        $supplier = $product->supplier ?: $source?->supplier;
        $path = []; $id = $product->category_id; $seen = [];
        while ($id && isset($categories[$id]) && !in_array($id, $seen)) {
            $seen[] = $id; array_unshift($path, $categories[$id]); $id = $categories[$id]->parent_id;
        }
        $parts = [];
        foreach ($config['components'] as $component) {
            $level = array_search($component, ['category', 'subcategory', 'subsubcategory'], true);
            if ($level !== false) {
                if (!isset($path[$level])) {
                    if ($level === 0) throw new \RuntimeException('Assign a category before generating this SKU.');
                    continue; // Products assigned directly to a parent have no lower level.
                }
                $value = trim((string) ($config['category_codes'][$path[$level]->id] ?? ''));
                if ($value === '') throw new \RuntimeException('Set a prefix for '.$path[$level]->name.' in Prefix setup.');
            } else {
                $value = match ($component) {
                    'static_prefix' => $config['static_prefix'], 'suffix' => $config['suffix'],
                    'sequence' => str_pad((string) $sequence, $config['sequence_padding'], '0', STR_PAD_LEFT),
                    'local_id' => (string) $product->id,
                    'supplier_code' => $supplier?->supplier_code,
                    'supplier_product_id' => $source?->supplier_product_id,
                    'supplier_sku' => $source?->product_code,
                    'date' => $product->created_at?->format('Ym') ?: now()->format('Ym'), default => '',
                };
                if (trim((string) $value) === '') throw new \RuntimeException(str_replace('_', ' ', ucfirst($component)).' is unavailable for this product.');
            }
            $value = preg_replace('/[^A-Za-z0-9]/', '', (string) $value);
            if ($value === '') throw new \RuntimeException('SKU components must contain letters or numbers.');
            $parts[] = $value;
        }
        $sku = implode($config['separator'] ?? '', $parts);
        $sku = $config['case'] === 'lower' ? strtolower($sku) : strtoupper($sku);
        if (strlen($sku) > 60) throw new \RuntimeException('The generated SKU exceeds 60 characters. Shorten the prefixes or remove a component.');
        return $sku;
    }

    /** Must run inside the product creation transaction, after its supplier link exists. */
    public function assignNew(Product $product, bool $imported = false): void
    {
        $config = $this->config();
        if (!$config['enabled'] || (!$imported && trim((string) $product->sku) !== '')) return;
        $this->lock();
        $config = $this->config();
        if (!$config['enabled']) return;
        $sequence = $this->nextSequence($config);
        $product->load('supplier', 'supplierLinks.supplierProduct.supplier');
        $categories = Category::all(['id', 'name', 'parent_id'])->keyBy('id')->all();
        do {
            try { $sku = $this->code($product, $config, $sequence, $categories); }
            catch (\RuntimeException $e) { throw ValidationException::withMessages(['sku' => $e->getMessage()]); }
            $collision = Product::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->where('id', '!=', $product->id)->exists();
            if ($collision && !in_array('sequence', $config['components'], true)) {
                throw ValidationException::withMessages(['sku' => 'The configured SKU already exists. Include a sequence or local product ID.']);
            }
            if ($collision) $sequence++;
        } while ($collision);
        $product->forceFill(['sku' => $sku, 'sku_managed' => true, 'sku_sequence' => in_array('sequence', $config['components'], true) ? $sequence : null])->save();
        $this->persist($config, in_array('sequence', $config['components'], true) ? $sequence + 1 : $sequence);
    }

    private function plan(array $config, string $scope, string $source): array
    {
        $query = Product::query()->orderBy('id');
        if ($scope === 'missing') $query->where(fn ($q) => $q->whereNull('sku')->orWhereRaw("TRIM(sku) = ''"));
        if ($source !== 'all') $query->where('source_type', $source);
        $products = $query->with('supplier', 'supplierLinks.supplierProduct.supplier')->get();
        $categories = Category::all(['id', 'name', 'parent_id'])->keyBy('id')->all();
        $occupied = Product::whereNotNull('sku')->get(['id', 'sku'])->mapWithKeys(fn ($p) => [strtolower($p->sku) => $p->id])->all();
        $sequence = $this->nextSequence($config); $changes = []; $errors = [];
        foreach ($products as $product) {
            // Reuse each product's allocated sequence when reconfiguring prefixes.
            $number = $product->sku_sequence ?? $sequence;
            try {
                $sku = $this->code($product, $config, $number, $categories);
                if (isset($occupied[strtolower($sku)]) && (int) $occupied[strtolower($sku)] !== $product->id) {
                    // A sequence can skip a reserved/custom code, while format-only duplicates are errors.
                    if (!$product->sku_sequence && in_array('sequence', $config['components'], true)) {
                        do { $number++; $sku = $this->code($product, $config, $number, $categories); } while (isset($occupied[strtolower($sku)]) && (int) $occupied[strtolower($sku)] !== $product->id);
                    } else throw new \RuntimeException('Duplicate SKU '.$sku.'. Include a sequence/local ID or use distinct prefixes.');
                }
                $occupied[strtolower($sku)] = $product->id;
                if (!$product->sku_sequence && in_array('sequence', $config['components'], true)) $sequence = $number + 1;
                if ($product->sku !== $sku || !$product->sku_managed) $changes[] = ['id' => $product->id, 'name' => $product->name, 'old_sku' => $product->sku, 'new_sku' => $sku, 'sequence' => in_array('sequence', $config['components'], true) ? $number : null];
            } catch (\RuntimeException $e) { $errors[] = ['id' => $product->id, 'name' => $product->name, 'message' => $e->getMessage()]; }
        }
        return ['total' => $products->count(), 'changed_count' => count($changes), 'changes' => $changes, 'errors' => $errors, 'next_sequence' => $sequence,
            'fingerprint' => hash('sha256', json_encode([$this->config(), $config, $scope, $source, $changes, $errors, $sequence, $products->map(fn ($p) => [$p->id, $p->sku, $p->category_id, $p->sku_sequence])->all()]))];
    }

    public function preview(array $config, string $scope, string $source, User $actor): array
    {
        $plan = $this->plan($config, $scope, $source);
        $token = Str::random(48);
        Cache::put('sku-preview:'.$token, ['actor_id' => $actor->id, 'config' => $config, 'scope' => $scope, 'source' => $source, 'fingerprint' => $plan['fingerprint']], now()->addMinutes(20));
        return ['token' => $token, 'total' => $plan['total'], 'changed_count' => $plan['changed_count'], 'errors' => array_slice($plan['errors'], 0, 50),
            'samples' => array_slice($plan['changes'], 0, 30), 'next_sequence' => $plan['next_sequence']];
    }

    public function apply(string $token, User $actor): int
    {
        $preview = Cache::get('sku-preview:'.$token);
        if (!$preview || (int) $preview['actor_id'] !== $actor->id) throw ValidationException::withMessages(['preview' => 'This preview expired. Preview the changes again.']);
        $count = DB::transaction(function () use ($preview, $actor) {
            // Product edits/imports lock products before the configuration counter as well.
            Product::orderBy('id')->lockForUpdate()->get(['id']);
            $this->lock();
            $plan = $this->plan($preview['config'], $preview['scope'], $preview['source']);
            if (!hash_equals($preview['fingerprint'], $plan['fingerprint'])) throw ValidationException::withMessages(['preview' => 'Products or numbering changed since preview. Preview the changes again.']);
            if ($plan['errors']) throw ValidationException::withMessages(['preview' => 'Resolve every preview error before applying.']);
            $before = $this->config();
            foreach ($plan['changes'] as $row) {
                Product::findOrFail($row['id'])->forceFill(['sku' => $row['new_sku'], 'sku_managed' => true, 'sku_sequence' => $row['sequence']])->save();
            }
            $this->persist($preview['config'], $plan['next_sequence']);
            $this->audit($actor, 'existing_updated', $preview['scope'].':'.$preview['source'], $before, $preview['config'], $plan['changes']);
            return $plan['changed_count'];
        }, 3);
        Cache::forget('sku-preview:'.$token);
        return $count;
    }
}
