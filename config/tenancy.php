<?php

declare(strict_types=1);

use App\Models\Company;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

return [
    'tenant_model' => Company::class,
    'id_generator' => null,

    'domain_model' => Domain::class,

    /**
     * The list of domains hosting your central app.
     *
     * Only relevant if you're using the domain or subdomain identification middleware.
     */
    /**
     * Central (Platform) domains. The Platform admin UI and its routes are host-scoped to
     * these hosts (1F-D); requests on any other host are treated as tenant/company hosts and
     * resolved to a Company by `tenant.resolve`.
     *
     * Configurable per environment via PLATFORM_CENTRAL_DOMAINS (comma-separated) so no host
     * (local or production) is baked into committed config. When unset, falls back to the
     * loopback hosts for a fresh checkout; set the real Platform host(s) in each environment's
     * .env (e.g. PLATFORM_CENTRAL_DOMAINS=laravelai.test for local Laragon).
     */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLATFORM_CENTRAL_DOMAINS', '127.0.0.1,localhost'))
    ))),

    /**
     * Platform-managed base domain for tenant subdomains (e.g. "{label}.{base_domain}").
     * NOT finalized for production — supplied via env so no real domain is hardcoded.
     * Subdomain construction/validation fails safely when this is not configured.
     */
    'base_domain' => env('PLATFORM_BASE_DOMAIN'),

    /**
     * Spike bootstrappers only: database switching + queue tenant payload.
     * Cache/filesystem/Redis tenancy are intentionally disabled.
     */
    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    /**
     * Database tenancy config. Used by DatabaseTenancyBootstrapper.
     */
    'database' => [
        'central_connection' => 'platform',

        /**
         * Connection used as a "template" for the dynamically created tenant database connection.
         * Note: don't name your template connection tenant. That name is reserved by package.
         */
        'template_tenant_connection' => 'workspace',

        /**
         * Not used when Company maps getInternal('db_name') to database_name.
         * Kept empty so Stancl cannot invent an alternate naming convention.
         */
        'prefix' => '',
        'suffix' => '',

        /**
         * TenantDatabaseManagers are classes that handle the creation & deletion of tenant databases.
         * Spike does not call CreateDatabase; managers remain for connection config only.
         */
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [],

    /**
     * Disable package tenant asset routes for this spike.
     */
    'routes' => false,

    /**
     * Test-only: allow CREATE/DROP of workspace_{ulid} databases in the spike suite.
     * Enabled via phpunit.xml; never enable against a live platform database.
     */
    'spike_allow_test_databases' => (bool) env('TENANCY_SPIKE_ALLOW_TEST_DATABASES', false),

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
    ],
];
