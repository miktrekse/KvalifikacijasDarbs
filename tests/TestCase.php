<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without a compiled front-end build (npm run build)
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // Tests that travel in time never leak a frozen clock into the next test
        Carbon::setTestNow();

        parent::tearDown();
    }
}
