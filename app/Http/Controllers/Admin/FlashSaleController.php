<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class FlashSaleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $flashProducts = Product::with('images', 'category', 'supplierLinks.supplierProduct.supplier')
            ->where('is_flash_sale', true)
            ->orderBy('flash_sale_position')
            ->orderBy('id')
            ->get();

        $available = Product::with('images', 'category', 'supplierLinks.supplierProduct.supplier')
            ->published()
            ->where('is_flash_sale', false)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhereHas('supplierLinks.supplierProduct', function ($supplierProduct) use ($search) {
                            $supplierProduct->where('name', 'like', "%{$search}%")
                                ->orWhere('product_code', 'like', "%{$search}%")
                                ->orWhere('supplier_product_id', 'like', "%{$search}%")
                                ->orWhere('supplier_category_key', 'like', "%{$search}%")
                                ->orWhere('brand', 'like', "%{$search}%")
                                ->orWhereHas('supplier', function ($supplier) use ($search) {
                                    $supplier->where('name', 'like', "%{$search}%")
                                        ->orWhere('key', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $endsAt = setting('flash_sale_ends_at');

        return Inertia::render('Admin/FlashSale/Index', [
            'flashProducts' => $flashProducts,
            'available'     => $available,
            'q'             => $request->input('q'),
            'endsAt'        => $endsAt,
            'timerExpired'  => $this->timerExpired($endsAt),
            'homepageLimit' => 5,
        ]);
    }

    public function updateEndsAt(Request $request)
    {
        $data = $request->validate([
            'flash_sale_ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $raw = (string) ($data['flash_sale_ends_at'] ?? '');
        if ($raw !== '') {
            $raw = str_replace('T', ' ', $raw);
            if (strlen($raw) === 16) {
                $raw .= ':00';
            }
        }

        Setting::put('flash_sale_ends_at', $raw);
        Setting::forgetCache();

        return back()->with('status', 'Flash sale end time saved.');
    }

    public function add(Product $product)
    {
        $nextPos = (int) Product::where('is_flash_sale', true)->max('flash_sale_position') + 1;

        $product->update([
            'is_flash_sale'       => true,
            'flash_sale_position' => $nextPos,
        ]);

        return back()->with('status', "“{$product->name}” added to Flash Sale.");
    }

    public function remove(Product $product)
    {
        $product->update([
            'is_flash_sale'       => false,
            'flash_sale_position' => 0,
        ]);

        return back()->with('status', "“{$product->name}” removed from Flash Sale.");
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:products,id'],
            'bulk_action' => ['required', 'in:add,remove'],
        ]);

        $ids = $data['ids'];
        $count = count($ids);

        if ($data['bulk_action'] === 'add') {
            $nextPos = (int) Product::where('is_flash_sale', true)->max('flash_sale_position');
            foreach (Product::whereIn('id', $ids)->where('is_flash_sale', false)->orderBy('id')->get() as $product) {
                $product->update([
                    'is_flash_sale'       => true,
                    'flash_sale_position' => ++$nextPos,
                ]);
            }

            return back()->with('status', "{$count} product(s) added to Flash Sale.");
        }

        Product::whereIn('id', $ids)->where('is_flash_sale', true)->update([
            'is_flash_sale'       => false,
            'flash_sale_position' => 0,
        ]);

        return back()->with('status', "{$count} product(s) removed from Flash Sale.");
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:products,id'],
        ]);

        foreach (array_values($data['order']) as $i => $id) {
            Product::where('id', $id)->where('is_flash_sale', true)->update([
                'flash_sale_position' => $i,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Flash sale order updated.']);
        }

        return back()->with('status', 'Flash sale order updated.');
    }

    private function timerExpired(?string $endsAt): bool
    {
        $value = trim((string) $endsAt);

        if ($value === '') {
            return false;
        }

        try {
            return Carbon::parse($value)->isPast();
        } catch (Throwable) {
            return false;
        }
    }
}
