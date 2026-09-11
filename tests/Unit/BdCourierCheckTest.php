<?php

namespace Tests\Unit;

use App\Models\BdCourierCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BdCourierCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechecking_the_same_normalized_phone_updates_one_row(): void
    {
        $first = BdCourierCheck::record(
            '+88 01711-223344',
            $this->courierResult(60),
            'manual',
            customerName: 'First Customer'
        );
        $second = BdCourierCheck::record(
            '01711223344',
            $this->courierResult(80),
            'checkout',
            customerName: 'Latest Customer'
        );

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('bd_courier_checks', 1);
        $this->assertDatabaseHas('bd_courier_checks', [
            'phone' => '01711223344',
            'customer_name' => 'Latest Customer',
            'source' => 'checkout',
            'check_count' => 2,
            'success_ratio' => 80,
        ]);
    }

    private function courierResult(float $ratio): array
    {
        return [
            'status' => 'success',
            '_success_ratio' => $ratio,
            '_risk_level' => 'medium',
            'data' => [
                'summary' => [
                    'total_parcel' => 10,
                    'success_parcel' => (int) ($ratio / 10),
                    'cancelled_parcel' => 10 - (int) ($ratio / 10),
                    'success_ratio' => $ratio,
                ],
            ],
        ];
    }
}
