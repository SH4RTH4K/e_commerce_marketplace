<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class FlashSaleController extends Controller
{
    public function index(Request $request)
    {
        $flashProducts = Product::with('images', 'category')
            ->where('is_flash_sale', true)
            ->orderBy('flash_sale_position')
            ->orderBy('id')
            ->get();

        $available = Product::with('images', 'category')
            ->published()
            ->where('is_flash_sale', false)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('brand', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/FlashSale/Index', [
            'flashProducts' => $flashProducts,
            'available'     => $available,
            'q'             => $request->input('q'),
            'endsAt'        => setting('flash_sale_ends_at'),
            'homepageLimit' => 5,
        ]);
    }

    public function updateEndsAt(Request $request)
    {
        $data = $request->validate([
            'flash_sale_ends_at' => ['nullable', 'date'],
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
}
