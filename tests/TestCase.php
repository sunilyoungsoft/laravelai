<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardPlatformTestingDatabase();
    }

    /**
     * Prevent RefreshDatabase / migrations from destroying the live platform DB.
     */
    protected function guardPlatformTestingDatabase(): void
    {
        $database = (string) config('database.connections.platform.database');

        if ($database !== 'laravelai_platform_testing') {
            throw new RuntimeException(
                "Unsafe platform database for tests: [{$database}]. ".
                'Tests must use PLATFORM_DB_DATABASE=laravelai_platform_testing.'
            );
        }
    }
}
