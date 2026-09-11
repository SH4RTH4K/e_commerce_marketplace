<?php

namespace Tests\Unit;

use App\Services\Dropshipping\PriceBreakdown;
use App\Services\Dropshipping\PricingEngine;
use PHPUnit\Framework\TestCase;

class StructuredPricingEngineTest extends TestCase
{
    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PricingEngine();
    }

    public function test_required_acceptance_scenario_and_maximum_cap_toggle(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 10],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 20, 'cap_enabled' => true],
        );
        $price = $this->engine->calculate('490', '690', $rules);
        $this->assertSame(680.0, $price->regularSellingPrice);
        $this->assertSame(510.0, $price->calculatedMinimumPrice);
        $this->assertSame(710.0, $price->calculatedMaximumPrice);

        $rules['maximum']['cap_enabled'] = false;
        $this->assertSame(680.0, $this->engine->calculate(490, 690, $rules)->regularSellingPrice);
    }

    public function test_all_formula_adjustments_are_independent(): void
    {
        foreach ([
            'none' => ['none', 0, 490.0], 'plus fixed' => ['plus_fixed', 100, 590.0],
            'minus fixed' => ['minus_fixed', 10, 480.0], 'plus percentage' => ['plus_percent', 30, 637.0],
            'minus percentage' => ['minus_percent', 5, 465.5],
        ] as [$type, $value, $expected]) {
            $rules = $this->rules(
                selling: ['base' => 'supplier_cost', 'adjustment_type' => $type, 'adjustment_value' => $value],
                minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
                maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            );
            $this->assertSame($expected, $this->engine->calculate(490, 690, $rules)->regularSellingPrice, $type);
        }
    }

    public function test_formulas_can_use_supplier_maximum_and_minimum_cap_is_optional(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 10],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 220, 'cap_enabled' => false],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0, 'cap_enabled' => false],
        );
        $price = $this->engine->calculate(490, 690, $rules);
        $this->assertSame(680.0, $price->regularSellingPrice);
        $this->assertSame(710.0, $price->calculatedMinimumPrice);
        $this->assertContains('sale_below_minimum_cap_disabled', $price->warnings);
    }

    public function test_caps_apply_only_when_enabled_and_conflict_is_explicit(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 300],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 30, 'cap_enabled' => true],
        );
        $price = $this->engine->calculate(490, 690, $rules);
        $this->assertSame(660.0, $price->regularSellingPrice);
        $this->assertSame(PriceBreakdown::STATUS_VALID, $price->status);

        $rules['minimum']['adjustment_value'] = 200;
        $rules['maximum']['adjustment_value'] = 300;
        $conflict = $this->engine->calculate(490, 690, $rules);
        $this->assertSame(PriceBreakdown::STATUS_CONFLICT, $conflict->status);
        $this->assertSame('calculated_minimum_exceeds_maximum', $conflict->reason);
    }

    public function test_discount_and_rounding_are_applied_after_regular_price(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 10],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0, 'cap_enabled' => true],
        );
        $rules['discount'] = ['type' => 'fixed', 'value' => 30, 'protect_minimum' => true];
        $price = $this->engine->calculate(490, 690, $rules);
        $this->assertSame(650.0, $price->finalSalePrice);
        $this->assertSame(190.0, $price->regularProfit);
        $this->assertSame(160.0, $price->saleProfit);
    }

    public function test_percentage_discount_uses_regular_selling_price_after_caps(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 300],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 30, 'cap_enabled' => true],
        );
        $rules['discount'] = ['type' => 'percent', 'value' => 10, 'protect_minimum' => true];

        $price = $this->engine->calculate(490, 690, $rules);

        $this->assertSame(660.0, $price->regularSellingPrice);
        $this->assertSame(66.0, $price->discountAmount);
        $this->assertSame(594.0, $price->finalSalePrice);
    }

    public function test_discount_breakdown_shows_requested_discount_before_minimum_protection(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_percent', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 5, 'cap_enabled' => true],
        );
        $rules['discount'] = ['type' => 'percent', 'value' => 10, 'protect_minimum' => true];

        $price = $this->engine->calculate(450, 890, $rules);

        $this->assertSame(540.0, $price->regularSellingPrice);
        $this->assertSame(54.0, $price->requestedDiscountAmount);
        $this->assertSame(486.0, $price->priceAfterDiscount);
        $this->assertSame(0.0, $price->discountAmount);
        $this->assertSame(540.0, $price->finalSalePrice);
    }

    public function test_admin_can_choose_calculated_maximum_as_regular_price(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_percent', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 5, 'cap_enabled' => true],
        );
        $rules['regular_price_source'] = 'maximum';
        $rules['discount'] = ['type' => 'percent', 'value' => 10, 'protect_minimum' => true];

        $price = $this->engine->calculate(450, 890, $rules);

        $this->assertSame('maximum', $price->regularPriceSource);
        $this->assertSame(885.0, $price->regularSellingPrice);
        $this->assertSame(88.5, $price->requestedDiscountAmount);
        $this->assertSame(796.5, $price->finalSalePrice);
    }

    public function test_admin_can_choose_supplier_maximum_as_regular_price(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_percent', 'adjustment_value' => 20, 'cap_enabled' => true],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 5, 'cap_enabled' => true],
        );
        $rules['regular_price_source'] = 'supplier_maximum';

        $price = $this->engine->calculate(450, 890, $rules);

        $this->assertSame('supplier_maximum', $price->regularPriceSource);
        $this->assertSame(890.0, $price->regularSellingPrice);
    }

    public function test_discount_has_its_own_rounding_rule_after_the_discount_is_applied(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 10],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0, 'cap_enabled' => false],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0, 'cap_enabled' => false],
        );
        $rules['discount'] = ['type' => 'fixed', 'value' => 5, 'protect_minimum' => false];
        $rules['discount_rounding'] = ['mode' => 'down', 'increment' => 10];
        $price = $this->engine->calculate(490, 690, $rules);
        $this->assertSame(680.0, $price->regularSellingPrice);
        $this->assertSame(670.0, $price->finalSalePrice);
        $this->assertSame(10.0, $price->discountAmount);
    }

    public function test_selling_minimum_and_maximum_each_have_independent_rounding(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 1.25, 'rounding' => ['mode' => 'up', 'increment' => 5]],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'plus_fixed', 'adjustment_value' => 1.25, 'cap_enabled' => true, 'rounding' => ['mode' => 'down', 'increment' => 10]],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 1.25, 'cap_enabled' => true, 'rounding' => ['mode' => 'nearest', 'increment' => 5]],
        );
        $price = $this->engine->calculate(490, 690, $rules);
        $this->assertSame(495.0, $price->rawSellingPrice);
        $this->assertSame(490.0, $price->calculatedMinimumPrice);
        $this->assertSame(690.0, $price->calculatedMaximumPrice);
        $this->assertSame(495.0, $price->regularSellingPrice);
    }

    public function test_missing_selected_supplier_value_is_not_treated_as_zero(): void
    {
        $rules = $this->rules(
            selling: ['base' => 'supplier_maximum', 'adjustment_type' => 'minus_fixed', 'adjustment_value' => 10],
            minimum: ['base' => 'supplier_cost', 'adjustment_type' => 'none', 'adjustment_value' => 0],
            maximum: ['base' => 'supplier_maximum', 'adjustment_type' => 'none', 'adjustment_value' => 0],
        );
        $price = $this->engine->calculate(490, null, $rules);
        $this->assertSame(PriceBreakdown::STATUS_INVALID, $price->status);
        $this->assertSame('missing_supplier_maximum', $price->reason);
    }

    private function rules(array $selling, array $minimum, array $maximum): array
    {
        return compact('selling', 'minimum', 'maximum') + [
            'discount' => ['type' => 'fixed', 'value' => 0, 'protect_minimum' => true],
            'rounding' => ['mode' => 'none', 'increment' => 1],
        ];
    }
}
