<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerCrmService
{
    public const SEGMENTS = ['all', 'registered', 'guest', 'repeat', 'no_orders', 'inactive'];

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'segment' => ['nullable', 'string'],
            'activity' => ['nullable', 'string'], 'sort' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer'],
        ]);
        return [
            'q' => trim($data['q'] ?? ''),
            'segment' => in_array($data['segment'] ?? '', self::SEGMENTS, true) ? $data['segment'] : 'all',
            'activity' => in_array($data['activity'] ?? '', ['recent', 'dormant'], true) ? $data['activity'] : '',
            'sort' => in_array($data['sort'] ?? '', ['recent', 'spent', 'orders', 'name', 'registered'], true) ? $data['sort'] : 'recent',
            'per_page' => in_array((int) ($data['per_page'] ?? 20), [20, 50, 100], true) ? (int) ($data['per_page'] ?? 20) : 20,
        ];
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = str_replace([' ', '+', '-', '(', ')', '.', "\t", "\r", "\n"], '', $phone);
        if (! preg_match('/^[0-9]{7,15}$/', $phone)) return '';
        if (strlen($phone) === 15 && str_starts_with($phone, '0088')) return substr($phone, 4);
        return strlen($phone) === 13 && str_starts_with($phone, '88') ? substr($phone, 2) : $phone;
    }

    private function phoneSql(string $column): string
    {
        $clean = "COALESCE({$column}, '')";
        foreach ([' ', '+', '-', '(', ')', '.'] as $character) $clean = "REPLACE({$clean}, '{$character}', '')";
        foreach ([9, 10, 13] as $character) $clean = "REPLACE({$clean}, CHAR({$character}), '')";
        $valid = DB::getDriverName() === 'sqlite' ? "{$clean} NOT GLOB '*[^0-9]*'" : "{$clean} REGEXP '^[0-9]+$'";
        return "CASE WHEN LENGTH({$clean}) BETWEEN 7 AND 15 AND {$valid} THEN CASE WHEN LENGTH({$clean}) = 15 AND SUBSTR({$clean}, 1, 4) = '0088' THEN SUBSTR({$clean}, 5) WHEN LENGTH({$clean}) = 13 AND SUBSTR({$clean}, 1, 2) = '88' THEN SUBSTR({$clean}, 3) ELSE {$clean} END ELSE '' END";
    }

    private function accounts(): Builder
    {
        return DB::table('users')
            ->whereNotIn(DB::raw("LOWER(TRIM(COALESCE(role, 'customer')))"), User::STAFF_ROLES)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('roles')
                    ->whereColumn('roles.key', 'users.role');
            })
            ->select('id as account_id', 'name', 'phone', 'email', 'address', 'city', 'postal_code', 'is_active', 'created_at as registered_at')
            ->selectRaw($this->phoneSql('phone').' AS phone_key');
    }

    private function uniquePhones(): Builder
    {
        return DB::query()->fromSub($this->accounts(), 'accounts')->where('phone_key', '!=', '')
            ->selectRaw('phone_key, MIN(account_id) AS account_id')->groupBy('phone_key')->havingRaw('COUNT(*) = 1');
    }

    /** Staff-created orders belong to the buyer, never to the staff account. */
    public function identifiedOrders(): Builder
    {
        $orders = DB::table('orders as o')->select('o.*')->selectRaw($this->phoneSql('o.customer_phone').' AS phone_key');
        $linked = DB::query()->fromSub($orders, 'o')
            ->leftJoinSub($this->accounts(), 'owner', 'o.user_id', '=', 'owner.account_id')
            ->leftJoinSub($this->uniquePhones(), 'matched', 'o.phone_key', '=', 'matched.phone_key')
            ->select('o.*')->selectRaw('COALESCE(owner.account_id, matched.account_id) AS crm_account_id');
        return DB::query()->fromSub($linked, 'linked')->select('linked.*')
            ->selectRaw("CASE WHEN crm_account_id IS NULL THEN phone_key ELSE NULL END AS guest_phone")
            ->selectRaw("CASE WHEN crm_account_id IS NULL AND phone_key = '' THEN id ELSE NULL END AS guest_order_id");
    }

    private function history(): Builder
    {
        return DB::query()->fromSub($this->identifiedOrders(), 'history')
            ->select('crm_account_id', 'guest_phone', 'guest_order_id')
            ->selectRaw("COUNT(*) AS orders_count, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count, SUM(CASE WHEN status NOT IN ('cancelled', 'delivered') THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) AS total_spent, SUM(CASE WHEN status NOT IN ('cancelled', 'delivered') THEN total ELSE 0 END) AS open_value")
            ->selectRaw("MIN(created_at) AS first_order_at, MAX(created_at) AS last_order_at, MAX(id) AS last_order_id, MAX(CASE WHEN NULLIF(TRIM(customer_email), '') IS NOT NULL THEN id ELSE NULL END) AS email_order_id")
            ->groupBy('crm_account_id', 'guest_phone', 'guest_order_id');
    }

    public function customers(): Builder
    {
        $accounts = DB::query()->fromSub($this->accounts(), 'a')
            ->leftJoinSub($this->history(), 'h', 'a.account_id', '=', 'h.crm_account_id')
            ->select('a.account_id', 'a.name as customer_name', 'a.phone as customer_phone', 'a.email as customer_email', 'a.address', 'a.city', 'a.postal_code', 'a.is_active', 'a.registered_at', 'a.phone_key')
            ->selectRaw('NULL AS guest_phone, NULL AS guest_order_id');
        $guests = DB::query()->fromSub($this->history(), 'h')->whereNull('h.crm_account_id')
            ->join('orders as latest', 'h.last_order_id', '=', 'latest.id')
            ->leftJoin('orders as contact', 'h.email_order_id', '=', 'contact.id')
            ->selectRaw('NULL AS account_id')->addSelect('latest.customer_name', 'latest.customer_phone', 'contact.customer_email', 'latest.shipping_address as address', 'latest.city', 'latest.postal_code')
            ->selectRaw('NULL AS is_active, NULL AS registered_at, h.guest_phone AS phone_key')
            ->addSelect('h.guest_phone', 'h.guest_order_id');
        foreach ([$accounts, $guests] as $query) {
            foreach (['orders_count', 'delivered_count', 'cancelled_count', 'open_count', 'total_spent', 'open_value'] as $field) $query->selectRaw("COALESCE(h.{$field}, 0) AS {$field}");
            $query->addSelect('h.first_order_at', 'h.last_order_at');
        }
        return DB::query()->fromSub($accounts->unionAll($guests), 'customers');
    }

    public function filtered(array $filters): Builder
    {
        $query = $this->customers();
        if ($filters['q'] !== '') {
            $phone = self::normalizePhone($filters['q']);
            $query->where(function ($query) use ($filters, $phone) {
                $query->where('customer_name', 'like', '%'.$filters['q'].'%')->orWhere('customer_phone', 'like', '%'.$filters['q'].'%')
                    ->orWhere('customer_email', 'like', '%'.$filters['q'].'%')->orWhere('city', 'like', '%'.$filters['q'].'%');
                if ($phone !== '') $query->orWhere('phone_key', $phone);
            });
        }
        if ($filters['activity'] === 'recent') $query->where('last_order_at', '>=', now()->subDays(30));
        if ($filters['activity'] === 'dormant') $query->where('last_order_at', '<', now()->subDays(90));
        return $query;
    }

    public function segment(Builder $query, string $segment): Builder
    {
        match ($segment) {
            'registered' => $query->whereNotNull('account_id'), 'guest' => $query->whereNull('account_id'),
            'repeat' => $query->where('delivered_count', '>=', 2), 'no_orders' => $query->where('orders_count', 0),
            'inactive' => $query->whereNotNull('account_id')->where('is_active', false), default => null,
        };
        return $query;
    }

    public function sort(Builder $query, string $sort): Builder
    {
        match ($sort) {
            'spent' => $query->orderByDesc('total_spent'), 'orders' => $query->orderByDesc('orders_count'),
            'name' => $query->orderBy('customer_name'), 'registered' => $query->orderByDesc('registered_at'),
            default => $query->orderByDesc('last_order_at')->orderByDesc('registered_at'),
        };
        return $query->orderBy('account_id')->orderBy('guest_phone')->orderBy('guest_order_id');
    }

    public function row(object $customer): array
    {
        $row = (array) $customer;
        $row['key'] = $row['account_id'] ? 'account-'.$row['account_id'] : ($row['guest_phone'] !== '' ? 'phone-'.$row['guest_phone'] : 'order-'.$row['guest_order_id']);
        $row['profile_url'] = '/admin/customers/profiles/'.$row['key'];
        $row['is_active'] = $row['account_id'] ? (bool) $row['is_active'] : null;
        foreach (['orders_count', 'delivered_count', 'cancelled_count', 'open_count'] as $field) $row[$field] = (int) $row[$field];
        foreach (['first_order_at', 'last_order_at', 'registered_at'] as $field) $row[$field.'_formatted'] = $row[$field] ? Carbon::parse($row[$field], 'UTC')->setTimezone('Asia/Dhaka')->format('d M Y') : null;
        return $row;
    }

    public function profile(string $key): array
    {
        $query = $this->customers();
        if (preg_match('/^account-([0-9]+)$/', $key, $match)) $query->where('account_id', (int) $match[1]);
        elseif (preg_match('/^phone-([0-9]{7,15})$/', $key, $match)) {
            $phone = self::normalizePhone($match[1]);
            $account = $this->uniquePhones()->where('phone_key', $phone)->first();
            $account ? $query->where('account_id', $account->account_id) : $query->whereNull('account_id')->where('guest_phone', $phone);
        } elseif (preg_match('/^order-([0-9]+)$/', $key, $match)) $query->where('guest_order_id', (int) $match[1]);
        else abort(404);
        $customer = $query->first();
        abort_unless($customer, 404);
        return $this->row($customer);
    }

    public function profileOrders(array $customer): Builder
    {
        $query = DB::query()->fromSub($this->identifiedOrders(), 'history');
        if ($customer['account_id']) return $query->where('crm_account_id', $customer['account_id']);
        return $customer['guest_phone'] !== '' ? $query->whereNull('crm_account_id')->where('guest_phone', $customer['guest_phone']) : $query->where('guest_order_id', $customer['guest_order_id']);
    }

    public function noteKeys(array $customer): array
    {
        $keys = [$customer['key']];
        if ($customer['account_id'] && $customer['phone_key'] !== '' && $this->uniquePhones()->where('phone_key', $customer['phone_key'])->exists()) $keys[] = 'phone-'.$customer['phone_key'];
        return $keys;
    }
}
