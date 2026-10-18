<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropshipSupplier;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\SupplierAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierController extends Controller
{
    private const TYPES = ['local', 'wholesale', 'manufacturer', 'dropshipping', 'other'];

    // Integration credentials and settings never enter core supplier payloads or audits.
    private const FIELDS = [
        'name', 'supplier_code', 'supplier_type', 'contact_person', 'mobile', 'whatsapp', 'email',
        'website', 'business_address', 'city', 'district', 'division', 'country', 'business_name',
        'trade_license', 'tax_vat_bin', 'payment_terms', 'notes',
    ];

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $suppliers = $this->supplierQuery($filters)->withCount('localProducts')->orderBy('name')
            ->paginate(25)->withQueryString()->through(fn (DropshipSupplier $supplier) => $this->summary($supplier));

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers, 'filters' => $filters, 'types' => self::TYPES,
            'canManage' => $request->user()->hasPermission('suppliers.manage'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Suppliers/Form', ['supplier' => null, 'types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $supplier = DB::transaction(function () use ($data, $request) {
            $supplier = new DropshipSupplier();
            $supplier->forceFill([
                ...$data, 'key' => 'supplier-'.Str::lower((string) Str::ulid()),
                'driver_key' => 'manual', 'base_url' => null, 'is_active' => true,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ])->save();

            if (! $supplier->supplier_code) {
                $base = 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT);
                $code = $base;
                $suffix = 2;
                while (DropshipSupplier::withTrashed()->where('supplier_code', $code)->exists()) {
                    $code = $base.'-'.$suffix++;
                }
                $supplier->forceFill(['supplier_code' => $code])->save();
            }
            $this->audit($supplier, $request, 'created', null);

            return $supplier;
        });

        if ($request->expectsJson()) {
            return response()->json(['supplier' => $this->summary($supplier)], 201);
        }

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier created.');
    }

    public function show(Request $request, DropshipSupplier $supplier)
    {
        $supplier->loadCount('localProducts');
        $products = Product::query()->where('supplier_id', $supplier->id)->with('category')->latest('id')
            ->paginate(20, ['*'], 'products_page')->withQueryString()->through(fn (Product $product) => [
                'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
                'source_type' => $product->source_type, 'stock_quantity' => (int) $product->stock_quantity,
                'category' => $product->category?->name,
            ]);
        $history = InventoryAdjustment::query()->where('supplier_id', $supplier->id)->with('user:id,name')->latest('id')
            ->paginate(20, ['*'], 'history_page')->withQueryString()->through(fn ($item) => [
                'id' => $item->id, 'product_name' => $item->product_name, 'sku' => $item->sku,
                'operation' => $item->operation, 'change' => $item->change, 'stock_before' => $item->stock_before,
                'stock_after' => $item->stock_after, 'reason' => $item->reason, 'performed_by' => $item->actor_name ?: $item->user?->name,
                'movement_type' => $item->movement_type, 'reference' => $item->reference, 'stock_scope' => $item->stock_scope,
                'created_at' => $item->created_at?->toIso8601String(),
            ]);
        $audits = SupplierAudit::query()->where('supplier_id', $supplier->id)->with('actor:id,name')->latest('id')
            ->paginate(15, ['*'], 'audit_page')->withQueryString()->through(fn (SupplierAudit $audit) => [
                'id' => $audit->id, 'event' => $audit->event, 'actor' => $audit->actor_name ?: $audit->actor?->name,
                'changed_fields' => array_values(array_intersect(array_keys($audit->after ?? []), [...self::FIELDS, 'is_active', 'supplier_id'])),
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => $this->details($supplier), 'products' => $products, 'history' => $history, 'audits' => $audits,
            'metrics' => $this->metrics($supplier), 'canManage' => $request->user()->hasPermission('suppliers.manage'),
            'canViewProducts' => $request->user()->hasPermission('products.view'),
            'canManageDropshipping' => config('dropshipping.enabled') && $request->user()->hasPermission('dropshipping.manage'),
        ]);
    }

    public function edit(DropshipSupplier $supplier)
    {
        return Inertia::render('Admin/Suppliers/Form', ['supplier' => $this->details($supplier), 'types' => self::TYPES]);
    }

    public function update(Request $request, DropshipSupplier $supplier)
    {
        $data = $this->validated($request, $supplier);
        DB::transaction(function () use ($supplier, $data, $request) {
            $supplier = DropshipSupplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $before = $this->auditValues($supplier);
            $supplier->forceFill([...$data, 'updated_by' => $request->user()->id])->save();
            $this->audit($supplier, $request, 'updated', $before);
        });

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier updated.');
    }

    public function toggle(Request $request, DropshipSupplier $supplier)
    {
        $active = DB::transaction(function () use ($supplier, $request) {
            $supplier = DropshipSupplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $before = $this->auditValues($supplier);
            $supplier->forceFill(['is_active' => ! $supplier->is_active, 'updated_by' => $request->user()->id])->save();
            $this->audit($supplier, $request, $supplier->is_active ? 'activated' : 'deactivated', $before);

            return $supplier->is_active;
        });

        return back()->with('status', $active ? 'Supplier activated.' : 'Supplier deactivated.');
    }

    public function report(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->supplierQuery($filters)->withCount([
            'localProducts',
            'localProducts as manual_products_count' => fn (Builder $q) => $q->where('source_type', 'manual'),
            'localProducts as dropshipping_products_count' => fn (Builder $q) => $q->where('source_type', 'dropshipping'),
            'localProducts as other_products_count' => fn (Builder $q) => $q->where('source_type', 'other'),
        ])->addSelect(['inventory_references_count' => InventoryAdjustment::query()->selectRaw('COUNT(*)')
            ->whereColumn('supplier_id', 'dropship_suppliers.id')])->orderBy('name');

        if ($request->string('format')->toString() === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['Code', 'Supplier', 'Type', 'Status', 'Linked products', 'Manual products', 'Dropshipping products', 'Other products', 'Inventory references']);
                foreach ($query->cursor() as $supplier) {
                    fputcsv($stream, array_map($this->csvValue(...), [
                        $this->code($supplier), $supplier->name, $this->type($supplier), $supplier->is_active ? 'Active' : 'Inactive',
                        $supplier->local_products_count, $supplier->manual_products_count, $supplier->dropshipping_products_count,
                        $supplier->other_products_count, $supplier->inventory_references_count,
                    ]));
                }
                fclose($stream);
            }, 'supplier-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        return Inertia::render('Admin/Suppliers/Report', [
            'suppliers' => $query->paginate(25)->withQueryString()->through(fn ($supplier) => [
                ...$this->summary($supplier), 'manual_products_count' => (int) $supplier->manual_products_count,
                'dropshipping_products_count' => (int) $supplier->dropshipping_products_count,
                'other_products_count' => (int) $supplier->other_products_count,
                'inventory_references_count' => (int) $supplier->inventory_references_count,
            ]), 'filters' => $filters, 'types' => self::TYPES,
            'supplierOptions' => DropshipSupplier::orderBy('name')->get(['id', 'name', 'supplier_code']),
        ]);
    }

    private function validated(Request $request, ?DropshipSupplier $supplier = null): array
    {
        if ($request->filled('supplier_code')) {
            $request->merge(['supplier_code' => strtoupper(trim($request->string('supplier_code')->toString()))]);
        }
        $rules = [
            'name' => ['required', 'string', 'max:120'], 'supplier_type' => ['required', Rule::in(self::TYPES)],
            'supplier_code' => [$supplier ? 'sometimes' : 'nullable', $supplier ? 'required' : 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', Rule::unique('dropship_suppliers', 'supplier_code')->ignore($supplier?->id)],
            'contact_person' => ['nullable', 'string', 'max:120'], 'mobile' => ['nullable', 'string', 'max:40'], 'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'], 'website' => ['nullable', 'url:http,https', 'max:255'], 'business_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:80'], 'district' => ['nullable', 'string', 'max:80'], 'division' => ['nullable', 'string', 'max:80'], 'country' => ['nullable', 'string', 'max:80'],
            'business_name' => ['nullable', 'string', 'max:160'], 'trade_license' => ['nullable', 'string', 'max:120'], 'tax_vat_bin' => ['nullable', 'string', 'max:120'], 'payment_terms' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:5000'],
        ];
        $data = $request->validate($rules);
        if (array_key_exists('country', $data) && ! $data['country']) $data['country'] = 'Bangladesh';

        return $data;
    }

    private function filters(Request $request): array
    {
        return ['q' => trim($request->string('q')->toString()), 'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(), 'supplier_id' => $request->integer('supplier_id') ?: ''];
    }

    private function supplierQuery(array $filters): Builder
    {
        return DropshipSupplier::query()
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$filters['q']}%")->orWhere('supplier_code', 'like', "%{$filters['q']}%")
                ->orWhere('contact_person', 'like', "%{$filters['q']}%")->orWhere('mobile', 'like', "%{$filters['q']}%")
                ->orWhere('email', 'like', "%{$filters['q']}%")))
            ->when(in_array($filters['type'], self::TYPES, true), fn (Builder $q) => $filters['type'] === 'local'
                ? $q->whereIn('supplier_type', ['local', 'manual']) : $q->where('supplier_type', $filters['type']))
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn (Builder $q) => $q->where('is_active', $filters['status'] === 'active'))
            ->when($filters['supplier_id'], fn (Builder $q) => $q->whereKey($filters['supplier_id']));
    }

    private function summary(DropshipSupplier $supplier): array
    {
        return ['id' => $supplier->id, 'name' => $supplier->name, 'supplier_code' => $this->code($supplier),
            'supplier_type' => $this->type($supplier), 'contact_person' => $supplier->contact_person, 'mobile' => $supplier->mobile,
            'email' => $supplier->email, 'is_active' => (bool) $supplier->is_active,
            'products_count' => (int) ($supplier->local_products_count ?? $supplier->localProducts()->count()),
            'integration' => $this->integration($supplier)];
    }

    private function details(DropshipSupplier $supplier): array
    {
        return [...$supplier->only(self::FIELDS), ...$this->summary($supplier),
            'created_at' => $supplier->created_at?->toIso8601String(), 'updated_at' => $supplier->updated_at?->toIso8601String(),
            'created_by' => $supplier->created_by ? User::find($supplier->created_by)?->name : null,
            'updated_by' => $supplier->updated_by ? User::find($supplier->updated_by)?->name : null];
    }

    private function metrics(DropshipSupplier $supplier): array
    {
        $counts = $supplier->localProducts()->selectRaw('source_type, COUNT(*) AS total')->groupBy('source_type')->pluck('total', 'source_type');

        return ['products' => (int) $supplier->local_products_count, 'manual' => (int) ($counts['manual'] ?? 0),
            'dropshipping' => (int) ($counts['dropshipping'] ?? 0), 'other' => (int) ($counts['other'] ?? 0),
            'inventory_references' => InventoryAdjustment::where('supplier_id', $supplier->id)->count()];
    }

    private function code(DropshipSupplier $supplier): string
    {
        return $supplier->supplier_code ?: 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT);
    }

    private function type(DropshipSupplier $supplier): string
    {
        return $supplier->supplier_type === 'manual' ? 'local' : $supplier->supplier_type;
    }

    private function integration(DropshipSupplier $supplier): ?string
    {
        return $supplier->hasIntegration()
            ? $supplier->driver_key.' Dropshipping' : null;
    }

    private function auditValues(DropshipSupplier $supplier): array
    {
        return [...$supplier->only(self::FIELDS), 'is_active' => (bool) $supplier->is_active];
    }

    private function audit(DropshipSupplier $supplier, Request $request, string $event, ?array $before): void
    {
        $after = $this->auditValues($supplier);
        if ($before !== null) {
            $changed = array_keys(array_filter($after, fn ($value, $key) => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
            $before = array_intersect_key($before, array_flip($changed));
            $after = array_intersect_key($after, array_flip($changed));
            if (! $changed) return;
        }
        SupplierAudit::create(['supplier_id' => $supplier->id, 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name,
            'event' => $event, 'before' => $before, 'after' => $after, 'created_at' => now()]);
    }

    private function csvValue(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
