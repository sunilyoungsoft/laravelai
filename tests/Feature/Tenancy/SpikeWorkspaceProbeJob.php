<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal sync-queue probe for the Stancl tenancy spike.
 */
class SpikeWorkspaceProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static ?string $observedDatabase = null;

    public static ?string $observedTenantKey = null;

    public static bool $platformCompaniesVisible = false;

    public function handle(): void
    {
        self::$observedDatabase = DB::connection()->getDatabaseName();
        self::$observedTenantKey = tenant()?->getTenantKey();

        self::$platformCompaniesVisible = Schema::connection(
            config('database.default')
        )->hasTable('companies');
    }

    public static function reset(): void
    {
        self::$observedDatabase = null;
        self::$observedTenantKey = null;
        self::$platformCompaniesVisible = false;
    }
}
