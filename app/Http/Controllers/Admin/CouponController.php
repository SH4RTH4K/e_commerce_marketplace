<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query()->orderByDesc('created_at');

        if ($term = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => $query->paginate(15)->withQueryString(),
            'q'       => $term ?? '',
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Coupons/Form', [
            'coupon' => new Coupon([
                'type'      => 'percentage',
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeCouponData($data);
        $data['is_active'] = $request->boolean('is_active');
        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Coupon $coupon)
    {
        return Inertia::render('Admin/Coupons/Form', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validateData($request, $coupon);
        $data = $this->sanitizeCouponData($data);
        $data['is_active'] = $request->boolean('is_active');
        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:coupons,id'],
            'bulk_action' => ['required', 'in:activate,deactivate,delete'],
        ]);

        $ids = $data['ids'];
        $count = count($ids);

        if ($data['bulk_action'] === 'activate') {
            Coupon::whereIn('id', $ids)->update(['is_active' => true]);

            return back()->with('status', "{$count} coupon(s) set to Active.");
        }

        if ($data['bulk_action'] === 'deactivate') {
            Coupon::whereIn('id', $ids)->update(['is_active' => false]);

            return back()->with('status', "{$count} coupon(s) set to Inactive.");
        }

        Coupon::whereIn('id', $ids)->delete();

        return back()->with('status', "{$count} coupon(s) deleted.");
    }

    private function validateData(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($coupon)],
            'description'      => ['nullable', 'string', 'max:255'],
            'type'             => ['required', Rule::in(Coupon::TYPES)],
            'value'            => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount'     => ['nullable', 'numeric', 'min:0'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'starts_at'        => ['nullable', 'date'],
            'expires_at'       => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        if ($data['type'] === 'percentage' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'Percentage cannot exceed 100.',
            ]);
        }

        return $data;
    }

    private function sanitizeCouponData(array $data): array
    {
        $data['code'] = strtoupper(trim((string) ($data['code'] ?? '')));
        $data['value'] = (float) ($data['value'] ?? 0);
        $data['min_order_amount'] = (isset($data['min_order_amount']) && trim((string) $data['min_order_amount']) !== '')
            ? (float) $data['min_order_amount']
            : null;
        $data['max_discount'] = (isset($data['max_discount']) && trim((string) $data['max_discount']) !== '')
            ? (float) $data['max_discount']
            : null;
        $data['max_uses'] = (isset($data['max_uses']) && trim((string) $data['max_uses']) !== '')
            ? (int) $data['max_uses']
            : null;
        $data['starts_at'] = (isset($data['starts_at']) && trim((string) $data['starts_at']) !== '')
            ? $data['starts_at']
            : null;
        $data['expires_at'] = (isset($data['expires_at']) && trim((string) $data['expires_at']) !== '')
            ? $data['expires_at']
            : null;
        $data['description'] = (isset($data['description']) && trim((string) $data['description']) !== '')
            ? trim((string) $data['description'])
            : null;

        return $data;
    }
}
