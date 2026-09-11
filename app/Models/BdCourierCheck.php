<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BdCourierCheck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'response' => 'array',
        'success_ratio' => 'float',
        'last_checked_at' => 'datetime',
    ];

    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone)) ?? '';

        return strlen($phone) === 13 && str_starts_with($phone, '88')
            ? substr($phone, 2)
            : $phone;
    }

    public static function record(
        string $phone,
        array $response,
        string $source = 'manual',
        ?int $orderId = null,
        ?string $customerName = null
    ): ?self
    {
        if (! Schema::hasTable('bd_courier_checks')) {
            return null;
        }

        $phone = self::normalizePhone($phone);
        $customerName = trim((string) $customerName);
        $summary = $response['data']['summary'] ?? [];
        $values = [
            'source' => $source,
            'order_id' => $orderId,
            'total_parcels' => (int) ($summary['total_parcel'] ?? 0),
            'successful_parcels' => (int) ($summary['success_parcel'] ?? 0),
            'cancelled_parcels' => (int) ($summary['cancelled_parcel'] ?? 0),
            'success_ratio' => (float) ($response['_success_ratio'] ?? $summary['success_ratio'] ?? 0),
            'risk_level' => (string) ($response['_risk_level'] ?? 'unknown'),
            'response' => $response,
            'last_checked_at' => now(),
            'updated_at' => now(),
        ];

        if ($customerName !== '' && Schema::hasColumn('bd_courier_checks', 'customer_name')) {
            $values['customer_name'] = $customerName;
        }

        DB::transaction(function () use ($phone, $values): void {
            DB::table('bd_courier_checks')->insertOrIgnore([
                'phone' => $phone,
                ...$values,
                'response' => json_encode($values['response'], JSON_THROW_ON_ERROR),
                'check_count' => 0,
                'created_at' => now(),
            ]);

            DB::table('bd_courier_checks')->where('phone', $phone)->update([
                ...$values,
                'response' => json_encode($values['response'], JSON_THROW_ON_ERROR),
            ]);
            DB::table('bd_courier_checks')->where('phone', $phone)->increment('check_count');
        });

        return self::query()->where('phone', $phone)->first();
    }
}
