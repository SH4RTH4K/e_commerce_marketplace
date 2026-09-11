<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\FakeOrderGuardController;
use App\Models\BdCourierCheck;
use ReflectionMethod;
use Tests\TestCase;

class FakeOrderGuardControllerTest extends TestCase
{
    public function test_history_risk_is_mapped_from_the_displayed_parcel_figures(): void
    {
        $check = new BdCourierCheck;
        $check->forceFill([
            'phone' => '01755588954',
            'total_parcels' => 2,
            'successful_parcels' => 1,
            'cancelled_parcels' => 1,
            'success_ratio' => 50,
            'risk_level' => 'unknown',
            'response' => [],
            'check_count' => 1,
        ]);

        $payload = (new ReflectionMethod(FakeOrderGuardController::class, 'historyPayload'))
            ->invoke(new FakeOrderGuardController, $check);

        $this->assertSame('high', $payload['risk_level']);
    }

    public function test_history_risk_is_unknown_when_there_are_no_parcels(): void
    {
        $check = new BdCourierCheck;
        $check->forceFill([
            'phone' => '01755588954',
            'total_parcels' => 0,
            'success_ratio' => 0,
            'risk_level' => 'danger',
            'response' => [],
        ]);

        $payload = (new ReflectionMethod(FakeOrderGuardController::class, 'historyPayload'))
            ->invoke(new FakeOrderGuardController, $check);

        $this->assertSame('unknown', $payload['risk_level']);
    }
}
