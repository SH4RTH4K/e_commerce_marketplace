<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropshipSupplier;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierController extends Controller
{
    private const TYPES = ['local', 'wholesale', 'manufacturer', 'dropshipping', 'other'];

    public function index(Request $request)
    {
        $filters = ['q' => trim($request->string('q')->toString()), 'type' => $request->string('type')->toString(), 'status' => $request->string('status')->toString()];
        $suppliers = DropshipSupplier::query()
            ->withCount('localProducts')
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$filters['q']}%")->orWhere('supplier_code', 'like', "%{$filters['q']}%")->orWhere('contact_person', 'like', "%{$filters['q']}%")))
            ->when(in_array($filters['type'], self::TYPES, true), fn ($query) => $query->where('supplier_type', $filters['type']))
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers->through(fn (DropshipSupplier $supplier) => $this->summary($supplier)),
            'filters' => $filters, 'types' => self::TYPES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Suppliers/Form', ['supplier' => null, 'types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $supplier = DropshipSupplier::create([...$data, 'key' => $this->key($data['name']), 'driver_key' => $data['supplier_type'] === 'dropshipping' ? null : 'manual', 'base_url' => null, 'is_active' => true]);
        $supplier->forceFill(['supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier created.');
    }

    public function show(DropshipSupplier $supplier)
    {
        $supplier->loadCount('localProducts');
        $products = Product::query()->where('supplier_id', $supplier->id)->with('category')->latest('id')->paginate(20)->withQueryString()->through(fn (Product $product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'source_type' => $product->source_type, 'stock_quantity' => (int) $product->stock_quantity, 'category' => $product->category?->name]);
        $history = InventoryAdjustment::query()->where('supplier_id', $supplier->id)->latest('id')->limit(30)->get()->map(fn ($item) => ['id' => $item->id, 'product_name' => $item->product_name, 'operation' => $item->operation, 'change' => $item->change, 'stock_after' => $item->stock_after, 'reason' => $item->reason, 'created_at' => $item->created_at?->toIso8601String()]);
        return Inertia::render('Admin/Suppliers/Show', ['supplier' => $this->summary($supplier), 'products' => $products, 'history' => $history]);
    }

    public function edit(DropshipSupplier $supplier)
    {
        return Inertia::render('Admin/Suppliers/Form', ['supplier' => $supplier->makeHidden(['api_key', 'secret_key']), 'types' => self::TYPES]);
    }

    public function update(Request $request, DropshipSupplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));
        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier updated.');
    }

    public function toggle(DropshipSupplier $supplier)
    {
        $supplier->forceFill(['is_active' => ! $supplier->is_active])->save();
        return back()->with('status', $supplier->is_active ? 'Supplier activated.' : 'Supplier deactivated.');
    }

    private function validated(Request $request, ?DropshipSupplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'supplier_type' => ['required', Rule::in(self::TYPES)],
            'contact_person' => ['nullable', 'string', 'max:120'], 'mobile' => ['nullable', 'string', 'max:40'], 'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'], 'website' => ['nullable', 'url', 'max:255'], 'business_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:80'], 'district' => ['nullable', 'string', 'max:80'], 'division' => ['nullable', 'string', 'max:80'], 'country' => ['nullable', 'string', 'max:80'],
            'business_name' => ['nullable', 'string', 'max:160'], 'trade_license' => ['nullable', 'string', 'max:120'], 'tax_vat_bin' => ['nullable', 'string', 'max:120'], 'payment_terms' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function key(string $name): string
    {
        $base = Str::slug($name) ?: 'supplier'; $key = $base; $index = 2;
        while (DropshipSupplier::withTrashed()->where('key', $key)->exists()) $key = $base.'-'.$index++;
        return $key;
    }

    private function summary(DropshipSupplier $supplier): array
    {
        return ['id' => $supplier->id, 'name' => $supplier->name, 'supplier_code' => $supplier->supplier_code ?: 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT), 'supplier_type' => $supplier->supplier_type === 'manual' ? 'local' : $supplier->supplier_type, 'contact_person' => $supplier->contact_person, 'mobile' => $supplier->mobile, 'email' => $supplier->email, 'is_active' => (bool) $supplier->is_active, 'products_count' => (int) ($supplier->local_products_count ?? $supplier->localProducts()->count()), 'integration' => $supplier->supplier_type === 'dropshipping' ? ($supplier->driver_key ?: null) : null];
    }
}
