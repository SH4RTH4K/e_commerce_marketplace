<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        // Preserve guests and buyers from placed orders, then append registered
        // customer accounts that have not placed an order yet.
        $orderCustomers = Order::query()
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->select('orders.customer_name', 'orders.customer_phone', 'orders.customer_email')
            ->selectRaw('MAX(orders.user_id) as account_id')
            ->selectRaw('MAX(users.is_active) as is_active')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(orders.total) as total_spent')
            ->selectRaw('MAX(orders.created_at) as last_order_at')
            ->groupBy('orders.customer_name', 'orders.customer_phone', 'orders.customer_email');

        if ($term = trim((string) $request->input('q'))) {
            $orderCustomers->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%");
            });
        }

        $registeredCustomers = User::query()
            ->where('role', 'customer')
            ->whereDoesntHave('orders')
            ->selectRaw('id as account_id')
            ->selectRaw('name as customer_name')
            ->selectRaw('phone as customer_phone')
            ->selectRaw('email as customer_email')
            ->selectRaw('is_active')
            ->selectRaw('0 as orders_count')
            ->selectRaw('0 as total_spent')
            ->selectRaw('created_at as last_order_at');

        if ($term) {
            $registeredCustomers->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $customers = $orderCustomers
            ->unionAll($registeredCustomers)
            ->orderByDesc('last_order_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Customers/Index', compact('customers', 'term'));
    }

    public function show(string $phone)
    {
        $orders = Order::where('customer_phone', $phone)->latest()->get();
        abort_if($orders->isEmpty(), 404);

        return Inertia::render('Admin/Customers/Show', [
            'orders'   => $orders,
            'customer' => $orders->first(),
        ]);
    }

    public function edit(User $customer)
    {
        $this->ensureCustomer($customer);

        return Inertia::render('Admin/Customers/Form', compact('customer'));
    }

    public function update(Request $request, User $customer)
    {
        $this->ensureCustomer($customer);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'email'       => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone'       => ['nullable', 'string', 'max:40'],
            'address'     => ['nullable', 'string', 'max:255'],
            'city'        => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_active'   => ['required', 'boolean'],
        ]);

        $customer->update($data);

        return redirect()->route('admin.customers.index')->with('status', 'Customer account updated.');
    }

    public function toggle(User $customer)
    {
        $this->ensureCustomer($customer);

        $customer->update(['is_active' => ! $customer->isActive()]);

        return back()->with('status', $customer->isActive() ? 'Customer account activated.' : 'Customer account deactivated.');
    }

    public function destroy(User $customer)
    {
        $this->ensureCustomer($customer);
        $customer->delete();

        return redirect()->route('admin.customers.index')->with('status', 'Customer account deleted.');
    }

    private function ensureCustomer(User $customer): void
    {
        abort_unless($customer->isCustomer(), 404);
    }
}
