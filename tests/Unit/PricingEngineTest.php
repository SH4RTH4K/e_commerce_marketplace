<?php

namespace Tests\Unit;

use App\Services\Dropshipping\PriceBreakdown;
use App\Services\Dropshipping\PricingEngine;
use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase
{
    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new PricingEngine();
    }

    public function test_it_applies_percent_and_fixed_markups(): void
    {
        $percent = $this->engine->calculate(100, 200, [
            'minimum_markup' => ['type' => 'percent', 'value' => 10],
            'selling_markup' => ['type' => 'percent', 'value' => 25],
        ]);
        $fixed = $this->engine->calculate(100, 200, [
            'minimum_markup' => ['type' => 'fixed', 'value' => 15],
            'selling_markup' => ['type' => 'fixed', 'value' => 30],
        ]);

        $this->assertSame(110.0, $percent->minimumPrice);
        $this->assertSame(125.0, $percent->finalPrice);
        $this->assertSame(115.0, $fixed->minimumPrice);
        $this->assertSame(130.0, $fixed->finalPrice);
    }

    public function test_it_rounds_the_selling_price_to_the_configured_increment(): void
    {
        $price = $this->engine->calculate(101, null, [
            'selling_markup' => ['type' => 'percent', 'value' => 13],
            'rounding' => ['mode' => 'up', 'increment' => 10],
        ]);

        $this->assertTrue($price->isValid());
        $this->assertSame(120.0, $price->finalPrice);
    }

    public function test_it_caps_a_selling_price_and_records_the_constraint(): void
    {
        $price = $this->engine->calculate(100, 130, [
            'minimum_markup' => ['type' => 'percent', 'value' => 10],
            'selling_markup' => ['type' => 'percent', 'value' => 50],
        ]);

        $this->assertTrue($price->isValid());
        $this->assertSame(130.0, $price->finalPrice);
        $this->assertContains('selling_markup_limited_by_ceiling', $price->warnings);
    }

    public function test_it_returns_a_conflict_when_the_minimum_exceeds_the_ceiling(): void
    {
        $price = $this->engine->calculate(100, 120, [
            'minimum_markup' => ['type' => 'percent', 'value' => 25],
        ]);

        $this->assertSame(PriceBreakdown::STATUS_CONFLICT, $price->status);
        $this->assertSame('minimum_exceeds_ceiling', $price->reason);
        $this->assertNull($price->finalPrice);
    }

    public function test_it_rejects_invalid_costs_and_never_returns_a_price_outside_the_constraints(): void
    {
        $invalid = $this->engine->calculate(null, 200);
        $price = $this->engine->calculate(99, 125, [
            'minimum_markup' => ['type' => 'percent', 'value' => 10],
            'selling_markup' => ['type' => 'percent', 'value' => 15],
            'rounding' => ['mode' => 'nearest', 'increment' => 10],
        ]);

        $this->assertSame(PriceBreakdown::STATUS_INVALID, $invalid->status);
        $this->assertSame('invalid_cost', $invalid->reason);
        $this->assertTrue($price->isValid());
        $this->assertGreaterThanOrEqual($price->minimumPrice, $price->finalPrice);
        $this->assertLessThanOrEqual($price->ceilingPrice, $price->finalPrice);
    }
}
