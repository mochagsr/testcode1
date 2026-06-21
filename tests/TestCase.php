<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase resets the database between tests but external cache
        // stores (redis/database) persist, leaking state across tests. Flush the
        // cache each test so the suite is isolated and reliable on any driver.
        Cache::flush();
    }
}
