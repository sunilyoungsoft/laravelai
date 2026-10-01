<?php

namespace Tests\Feature\Tenancy\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates/drops workspace_* databases only under explicit test safety guards.
 */
final class GuardedWorkspaceDatabase
{
    /** @var list<string> */
    private array $created = [];

    public function create(string $databaseName): void
    {
        $this->assertSafe($databaseName);

        DB::connection('platform')->statement(
            'CREATE DATABASE IF NOT EXISTS `'.$databaseName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $this->created[] = $databaseName;
    }

    public function drop(string $databaseName): void
    {
        $this->assertSafe($databaseName);

        DB::connection('platform')->statement('DROP DATABASE IF EXISTS `'.$databaseName.'`');

        $this->created = array_values(array_filter(
            $this->created,
            static fn (string $name): bool => $name !== $databaseName
        ));
    }

    /**
     * Track a workspace database name that was created outside this helper (e.g. by the
     * provisioning service under test) so dropTracked() cleans it up in tearDown.
     */
    public function trackExisting(string $databaseName): void
    {
        $this->assertSafe($databaseName);

        if (! in_array($databaseName, $this->created, true)) {
            $this->created[] = $databaseName;
        }
    }

    public function dropTracked(): void
    {
        foreach ($this->created as $databaseName) {
            $this->drop($databaseName);
        }
    }

    private function assertSafe(string $databaseName): void
    {
        if (config('database.connections.platform.database') !== 'laravelai_platform_testing') {
            throw new RuntimeException(
                'Refusing workspace DB operations: platform connection is not laravelai_platform_testing.'
            );
        }

        if (! config('tenancy.spike_allow_test_databases')) {
            throw new RuntimeException(
                'Refusing workspace DB operations: TENANCY_SPIKE_ALLOW_TEST_DATABASES is not enabled.'
            );
        }

        if (! preg_match('/^workspace_[0-9a-z]{26}$/', $databaseName)) {
            throw new RuntimeException(
                "Refusing workspace DB operations: invalid name [{$databaseName}]."
            );
        }
    }
}
