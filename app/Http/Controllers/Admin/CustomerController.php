<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerNote;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerCrmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerCrmService $crm)
    {
        $filters = $crm->filters($request);
        $query = $crm->filtered($filters);
        $totals = (clone $query)->selectRaw("COUNT(*) AS all_customers, SUM(CASE WHEN account_id IS NOT NULL THEN 1 ELSE 0 END) AS registered, SUM(CASE WHEN account_id IS NULL THEN 1 ELSE 0 END) AS guest, SUM(CASE WHEN delivered_count >= 2 THEN 1 ELSE 0 END) AS repeat_buyers, SUM(CASE WHEN orders_count = 0 THEN 1 ELSE 0 END) AS no_orders, SUM(CASE WHEN account_id IS NOT NULL AND is_active = 0 THEN 1 ELSE 0 END) AS inactive")->first();
        $summary = ['all' => (int) $totals->all_customers, 'repeat' => (int) $totals->repeat_buyers];
        foreach (['registered', 'guest', 'no_orders', 'inactive'] as $field) $summary[$field] = (int) $totals->$field;
        $crm->segment($query, $filters['segment']);
        $spent = (clone $query)->sum('total_spent');
        $crm->sort($query, $filters['sort']);
        return Inertia::render('Admin/Customers/Index', [
            'customers' => $query->paginate($filters['per_page'])->withQueryString()->through(fn ($customer) => $crm->row($customer)),
            'filters' => $filters, 'term' => $filters['q'], 'summary' => $summary, 'deliveredValue' => $spent,
            'approvalRequired' => customer_approval_required(),
        ]);
    }

    public function profile(Request $request, string $key, CustomerCrmService $crm)
    {
        $customer = $crm->profile($key);
        if ($key !== $customer['key']) return redirect($customer['profile_url']);
        $status = $request->input('order_status', 'all');
        if (! in_array($status, ['all', ...Order::STATUSES], true)) $status = 'all';
        $orders = $crm->profileOrders($customer);
        $latest = (clone $orders)->orderByDesc('created_at')->orderByDesc('id')->first();
        foreach (['address' => 'shipping_address', 'city' => 'city', 'postal_code' => 'postal_code'] as $field => $orderField) {
            if (empty($customer[$field])) $customer[$field] = $latest?->$orderField;
        }
        if ($status !== 'all') $orders->where('status', $status);
        $orders->orderByDesc('created_at')->orderByDesc('id');
        $notes = CustomerNote::whereIn('customer_key', $crm->noteKeys($customer))->latest('id')->paginate(10, ['*'], 'notes_page')->withQueryString()
            ->through(fn ($note) => ['id' => $note->id, 'body' => $note->body, 'author' => $note->author_name,
                'created_at' => $note->created_at->setTimezone('Asia/Dhaka')->format('d M Y, h:i A')]);
        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'orders' => $orders->paginate(15)->withQueryString()->through(fn ($order) => [
                'id' => $order->id, 'order_number' => $order->order_number, 'total' => $order->total, 'status' => $order->status,
                'payment_method' => $order->payment_method, 'payment_status' => $order->payment_status,
                'created_at_formatted' => $order->created_at ? Carbon::parse($order->created_at, 'UTC')->setTimezone('Asia/Dhaka')->format('d M Y, h:i A') : null,
            ]),
            'notes' => $notes, 'orderStatus' => $status,
            'returnTo' => $this->returnTo($request), 'approvalRequired' => customer_approval_required(),
            'canViewOrders' => $request->user()->hasPermission('orders.view'),
        ]);
    }

    public function show(string $phone, CustomerCrmService $crm)
    {
        $phone = CustomerCrmService::normalizePhone($phone);
        abort_if($phone === '', 404);
        return redirect($crm->profile('phone-'.$phone)['profile_url']);
    }

    public function note(Request $request, string $key, CustomerCrmService $crm)
    {
        $customer = $crm->profile($key);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        CustomerNote::create(['customer_key' => $customer['key'], 'user_id' => $request->user()->id,
            'author_name' => $request->user()->name, 'body' => $data['body']]);
        $status = $request->input('order_status', 'all');
        if (! in_array($status, ['all', ...Order::STATUSES], true)) $status = 'all';
        return redirect($customer['profile_url'].'?'.http_build_query(['return_to' => $this->returnTo($request), 'order_status' => $status]))
            ->with('status', 'Private customer note added.');
    }

    public function export(Request $request, CustomerCrmService $crm)
    {
        $filters = $crm->filters($request);
        $query = $crm->segment($crm->filtered($filters), $filters['segment']);
        $crm->sort($query, $filters['sort']);
        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Customer', 'Phone', 'Email', 'City', 'Type', 'Account status', 'Orders', 'Delivered orders', 'Cancelled orders', 'Delivered value', 'Open order value', 'Last order'], ',', '"', '');
            foreach ($query->lazy(200) as $customer) {
                $values = [$customer->customer_name, $customer->customer_phone, $customer->customer_email, $customer->city,
                    $customer->account_id ? 'Registered' : 'Guest', $customer->account_id ? ($customer->is_active ? 'Active' : 'Inactive') : '',
                    $customer->orders_count, $customer->delivered_count, $customer->cancelled_count, $customer->total_spent, $customer->open_value,
                    $customer->last_order_at ? Carbon::parse($customer->last_order_at, 'UTC')->setTimezone('Asia/Dhaka')->format('Y-m-d H:i') : ''];
                fputcsv($output, array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+@\-\t\r]/', $value) ? "'".$value : $value, $values), ',', '"', '');
            }
            fclose($output);
        }, 'customers-'.now('Asia/Dhaka')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function edit(Request $request, string $customer)
    {
        $customer = $this->findCustomer($customer);
        return Inertia::render('Admin/Customers/Form', ['customer' => $customer->only(['id', 'name', 'email', 'phone', 'address', 'city', 'postal_code', 'is_active']),
            'returnTo' => $this->returnTo($request), 'approvalRequired' => customer_approval_required()]);
    }

    public function update(Request $request, string $customer)
    {
        $customer = $this->findCustomer($customer);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'], 'postal_code' => ['nullable', 'string', 'max:20'], 'is_active' => ['required', 'boolean'],
        ]);
        $customer->update($data);
        return redirect($this->returnTo($request))->with('status', 'Customer account updated.');
    }

    public function toggle(Request $request, string $customer)
    {
        $customer = $this->findCustomer($customer);
        $data = $request->validate(['is_active' => ['sometimes', 'required', 'boolean']]);
        $customer->update(['is_active' => $data['is_active'] ?? ! $customer->isActive()]);
        return back()->with('status', $customer->isActive() ? 'Customer account activated.' : 'Customer account deactivated.');
    }

    public function destroy(string $customer)
    {
        $customer = $this->findCustomer($customer);
        DB::transaction(function () use ($customer) {
            CustomerNote::where('customer_key', 'account-'.$customer->id)->delete();
            $customer->delete();
        });
        return redirect()->route('admin.customers.index')->with('status', 'Customer account deleted. Existing orders were retained.');
    }

    private function findCustomer(string $identifier): User
    {
        $customer = ctype_digit($identifier) ? User::findOrFail($identifier) : User::where('email', $identifier)->firstOrFail();
        abort_unless($customer->isCustomer(), 404);
        return $customer;
    }

    private function returnTo(Request $request): string
    {
        $url = $request->input('return_to');
        return is_string($url) && ! preg_match('/[\x00-\x1f\x7f]/', $url)
            && preg_match('/^\/admin\/customers(?:\/profiles\/(?:account|phone|order)-[0-9]+)?(?:\?[^#]*)?$/', $url) ? $url : '/admin/customers';
    }
}
