<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        return Inertia::render('Storefront/Cart', [
            'items'    => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty'        => ['nullable', 'integer', 'min:1', 'max:99'],
            'variant'    => ['nullable', 'string', 'max:120'],
        ]);

        $product = Product::published()->findOrFail($data['product_id']);
        $qty     = (int) ($data['qty'] ?? 1);
        $variant = isset($data['variant']) && trim((string) $data['variant']) !== ''
            ? trim((string) $data['variant'])
            : null;
        $inCart  = $this->cart->qtyInCart($product->id, $variant);

        if ($product->stock_quantity < 1) {
            $message = "\"{$product->name}\" is out of stock.";
            if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->withInput()->withErrors(['cart' => $message])->with('error', $message);
        }

        if ($inCart + $qty > $product->stock_quantity) {
            $message = "Only {$product->stock_quantity} of \"{$product->name}\" available.";
            if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->withInput()->withErrors(['cart' => $message])->with('error', $message);
        }

        $this->cart->add($product->id, $qty, $variant);
        $message = "Added \"{$product->name}\" to cart.";

        if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'cart'    => $this->cart->toArray(),
                'drawer'  => view('storefront.partials.cart-drawer-items', [
                    'items'    => $this->cart->items(),
                    'subtotal' => $this->cart->subtotal(),
                ])->render(),
            ]);
        }

        return back()->withInput()->with('cart_open', true)->with('status', $message);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $lines = session('cart', []);
        $line  = $lines[$data['key']] ?? null;
        if ($line && (int) $data['qty'] > 0) {
            $product = Product::published()->find($line['product_id']);
            if ($product && (int) $data['qty'] > $product->stock_quantity) {
                $message = "Only {$product->stock_quantity} of \"{$product->name}\" available.";
                if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                    return response()->json(['ok' => false, 'message' => $message], 422);
                }

                return back()->withErrors(['cart' => $message]);
            }
        }

        $this->cart->update($data['key'], (int) $data['qty']);

        if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'ok'     => true,
                'cart'   => $this->cart->toArray(),
                'drawer' => view('storefront.partials.cart-drawer-items', [
                    'items'    => $this->cart->items(),
                    'subtotal' => $this->cart->subtotal(),
                ])->render(),
            ]);
        }

        return back();
    }

    /**
     * Replace the cart with this product and go straight to checkout (Order Now).
     */
    public function buyNow(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty'        => ['nullable', 'integer', 'min:1', 'max:99'],
            'variant'    => ['nullable', 'string', 'max:120'],
        ]);

        $product = Product::published()->findOrFail($data['product_id']);
        $qty     = (int) ($data['qty'] ?? 1);
        $variant = isset($data['variant']) && trim((string) $data['variant']) !== ''
            ? trim((string) $data['variant'])
            : null;

        if ($product->stock_quantity < 1) {
            $message = "\"{$product->name}\" is out of stock.";
            if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->withInput()->withErrors(['cart' => $message])->with('error', $message);
        }

        if ($qty > $product->stock_quantity) {
            $message = "Only {$product->stock_quantity} of \"{$product->name}\" available.";
            if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->withInput()->withErrors(['cart' => $message])->with('error', $message);
        }

        $this->cart->clear();
        $this->cart->add($product->id, $qty, $variant);

        if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'ok'       => true,
                'message'  => 'Redirecting to checkout…',
                'redirect' => route('checkout.show'),
                'cart'     => $this->cart->toArray(),
            ]);
        }

        return redirect()->route('checkout.show');
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['key' => ['required', 'string']]);
        $this->cart->remove($data['key']);

        if ($request->boolean('redirect_home_if_empty') && $this->cart->items()->isEmpty()) {
            return redirect()->route('home');
        }

        if (! $request->header('X-Inertia') && ($request->wantsJson() || $request->ajax())) {
            return response()->json([
                'ok'     => true,
                'cart'   => $this->cart->toArray(),
                'drawer' => view('storefront.partials.cart-drawer-items', [
                    'items'    => $this->cart->items(),
                    'subtotal' => $this->cart->subtotal(),
                ])->render(),
            ]);
        }

        return back();
    }

    public function removeMany(Request $request)
    {
        $data = $request->validate([
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['required', 'string'],
        ]);

        foreach ($data['keys'] as $key) {
            $this->cart->remove($key);
        }

        if ($request->boolean('redirect_home_if_empty') && $this->cart->items()->isEmpty()) {
            return redirect()->route('home');
        }

        return back();
    }
}
