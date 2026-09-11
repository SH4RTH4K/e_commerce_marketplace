<?php

namespace Tests\Unit\Dropshipping;

use App\Services\Dropshipping\Support\CategoryMappingMode;
use PHPUnit\Framework\TestCase;

class CategoryMappingModeTest extends TestCase
{
    public function test_only_manual_and_auto_mapping_modes_are_valid(): void
    {
        $this->assertTrue(CategoryMappingMode::isValid(CategoryMappingMode::MANUAL));
        $this->assertTrue(CategoryMappingMode::isValid(CategoryMappingMode::AUTO));
        $this->assertFalse(CategoryMappingMode::isValid('guessed'));
    }
}
