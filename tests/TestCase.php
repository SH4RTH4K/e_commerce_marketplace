<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Most feature tests assert Inertia's server-side view data. The
        // cache tests opt back in to exercise the production cache path.
        config(['storefront.page_cache.enabled' => false]);
    }
}
