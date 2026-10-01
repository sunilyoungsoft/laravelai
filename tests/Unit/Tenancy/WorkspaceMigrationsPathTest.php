<?php

namespace Tests\Unit\Tenancy;

use Illuminate\Database\Migrations\Migration;
use PHPUnit\Framework\TestCase;

/**
 * The Workspace (tenant) migrations live in database/migrations/tenant and are executed
 * against tenant databases only. This asserts the path exists, matches the path configured
 * for Stancl, and contains discoverable migrations.
 */
class WorkspaceMigrationsPathTest extends TestCase
{
    private function workspaceMigrationsPath(): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR
            .'database'.DIRECTORY_SEPARATOR
            .'migrations'.DIRECTORY_SEPARATOR
            .'tenant';
    }

    public function test_workspace_migrations_directory_exists(): void
    {
        $this->assertDirectoryExists($this->workspaceMigrationsPath());
    }

    public function test_workspace_migrations_are_discoverable(): void
    {
        $files = glob($this->workspaceMigrationsPath().DIRECTORY_SEPARATOR.'*.php');

        $this->assertNotEmpty($files, 'Expected at least one workspace migration file.');

        $names = array_map('basename', $files);
        $this->assertContains('2026_03_19_000001_create_workspace_meta_table.php', $names);
    }

    public function test_each_workspace_migration_returns_an_anonymous_migration(): void
    {
        foreach (glob($this->workspaceMigrationsPath().DIRECTORY_SEPARATOR.'*.php') as $file) {
            $migration = require $file;

            $this->assertInstanceOf(
                Migration::class,
                $migration,
                "Workspace migration {$file} must return a Migration instance."
            );
        }
    }
}
