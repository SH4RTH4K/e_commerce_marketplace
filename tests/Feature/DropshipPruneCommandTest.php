<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropshipPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_rejects_an_unsafe_retention_period(): void
    {
        $this->artisan('dropship:prune', ['--days' => 6])
            ->assertExitCode(1);
    }
}
