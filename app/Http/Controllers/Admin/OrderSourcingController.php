<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveOrderStockRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;

class OrderSourcingController extends Controller
{
    public function store(ReceiveOrderStockRequest $request, Order $order, OrderItem $item, InventoryStockService $stock): RedirectResponse
    {
        abort_unless((int) $item->order_id === (int) $order->id, 404);
        $data = $request->validated();
        $stock->receiveForOrder($request->user(), $order, $item, (int) $data['quantity'], (int) $data['expected_outstanding'], $data['reference'] ?? null);

        return back()->with('status', 'Stock received and allocated to this order. Review the order before confirming it.');
    }
}
