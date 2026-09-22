<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

class PlatformTestingDatabaseGuardTest extends TestCase
{
    public function test_guard_rejects_live_platform_database_name(): void
    {
        config(['database.connections.platform.database' => 'laravelai_platform']);

        try {
            $this->invokePlatformDatabaseGuard();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unsafe platform database for tests', $exception->getMessage());
        } finally {
            config(['database.connections.platform.database' => 'laravelai_platform_testing']);
        }
    }

    private function invokePlatformDatabaseGuard(): void
    {
        $method = new \ReflectionMethod(TestCase::class, 'guardPlatformTestingDatabase');
        $method->invoke($this);
    }
}
