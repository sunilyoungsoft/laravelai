<?php

namespace Tests\Feature\Platform;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\PlatformUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_uses_platform_connection(): void
    {
        $this->assertSame('platform', (new Company)->getConnectionName());
    }

    public function test_ulid_is_generated_for_company(): void
    {
        $company = Company::factory()->create();

        $this->assertSame(26, strlen($company->id));
    }

    public function test_default_status_is_pending(): void
    {
        $company = Company::factory()->create();

        $this->assertSame(CompanyStatus::Pending, $company->status);
        $this->assertSame('pending', $company->status->value);
    }

    public function test_status_casts_to_company_status_enum(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Suspended]);

        $this->assertInstanceOf(CompanyStatus::class, $company->status);
        $this->assertSame(CompanyStatus::Suspended, $company->status);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        Company::factory()->create(['slug' => 'acme']);

        $this->expectException(QueryException::class);

        Company::factory()->create(['slug' => 'acme']);
    }

    public function test_duplicate_database_name_is_rejected(): void
    {
        $existing = Company::factory()->create();

        $this->expectException(QueryException::class);

        Company::factory()->create([
            'database_name' => $existing->database_name,
        ]);
    }

    public function test_soft_deleted_slug_remains_reserved(): void
    {
        $company = Company::factory()->create(['slug' => 'acme']);
        $company->delete();

        $this->expectException(QueryException::class);

        Company::factory()->create(['slug' => 'acme']);
    }

    public function test_soft_deleted_database_name_remains_reserved(): void
    {
        $company = Company::factory()->create();
        $databaseName = $company->database_name;
        $company->delete();

        $this->expectException(QueryException::class);

        Company::factory()->create([
            'database_name' => $databaseName,
        ]);
    }

    public function test_same_name_allowed_for_multiple_companies(): void
    {
        Company::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme-one']);
        Company::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme-two']);

        $this->assertSame(2, Company::query()->where('name', 'Acme Corp')->count());
    }

    public function test_same_gst_number_allowed_for_multiple_companies(): void
    {
        Company::factory()->create(['gst_number' => 'GST123', 'slug' => 'gst-one']);
        Company::factory()->create(['gst_number' => 'GST123', 'slug' => 'gst-two']);

        $this->assertSame(2, Company::query()->where('gst_number', 'GST123')->count());
    }

    public function test_creator_relationship_works(): void
    {
        $creator = PlatformUser::factory()->create();
        $company = Company::factory()->create(['created_by' => $creator->id]);

        $this->assertTrue($company->creator->is($creator));
    }

    public function test_updater_relationship_works(): void
    {
        $creator = PlatformUser::factory()->create();
        $updater = PlatformUser::factory()->create();
        $company = Company::factory()->create([
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);

        $this->assertTrue($company->updater->is($updater));
    }

    public function test_created_by_foreign_key_uses_no_action(): void
    {
        $this->assertForeignKeyDeleteRule('companies', 'created_by', 'NO ACTION');
    }

    public function test_updated_by_foreign_key_uses_no_action(): void
    {
        $this->assertForeignKeyDeleteRule('companies', 'updated_by', 'NO ACTION');
    }

    public function test_database_name_always_corresponds_to_company_id(): void
    {
        $company = Company::factory()->create();

        $this->assertSame(
            'workspace_'.strtolower($company->id),
            $company->database_name
        );
    }

    public function test_sensitive_system_fields_are_not_mass_assignable(): void
    {
        $creator = PlatformUser::factory()->create();
        $id = (string) Str::ulid();

        $company = new Company;
        $company->setConnection('platform');
        $company->fill([
            'name' => 'Acme',
            'slug' => 'acme-mass',
            'database_name' => 'workspace_evil',
            'status' => CompanyStatus::Active->value,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
        $company->id = $id;
        $company->database_name = 'workspace_'.strtolower($id);
        $company->status = CompanyStatus::Pending;
        $company->created_by = $creator->id;
        $company->save();

        $company->refresh();

        $this->assertSame('workspace_'.strtolower($id), $company->database_name);
        $this->assertSame(CompanyStatus::Pending, $company->status);
        $this->assertSame($creator->id, $company->created_by);
        $this->assertNull($company->updated_by);
    }

    public function test_status_column_is_indexed(): void
    {
        $indexes = collect(DB::connection('platform')->select('SHOW INDEX FROM companies'))
            ->where('Column_name', 'status');

        $this->assertTrue($indexes->isNotEmpty());
    }

    private function assertForeignKeyDeleteRule(string $table, string $column, string $expectedRule): void
    {
        $database = config('database.connections.platform.database');

        $row = DB::connection('platform')->selectOne(
            'SELECT rc.DELETE_RULE
             FROM information_schema.KEY_COLUMN_USAGE kcu
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                 ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                 AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             WHERE kcu.TABLE_SCHEMA = ?
               AND kcu.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$database, $table, $column]
        );

        $this->assertNotNull($row);
        // MySQL reports NO ACTION as RESTRICT in information_schema for InnoDB.
        $this->assertContains($row->DELETE_RULE, [$expectedRule, 'RESTRICT']);
    }
}
