<?php

namespace Tests\Feature\Platform;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Services\Platform\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_company_with_pending_status_and_derived_database_name(): void
    {
        $creator = PlatformUser::factory()->create();

        $company = app(CompanyService::class)->create([
            'name' => '  Acme Corp  ',
            'slug' => '  Acme-Corp  ',
            'email' => 'hello@acme.test',
            'country_code' => 'in',
        ], $creator);

        $this->assertSame('Acme Corp', $company->name);
        $this->assertSame('acme-corp', $company->slug);
        $this->assertSame('IN', $company->country_code);
        $this->assertSame(CompanyStatus::Pending, $company->status);
        $this->assertSame('workspace_'.strtolower($company->id), $company->database_name);
        $this->assertSame($creator->id, $company->created_by);
        $this->assertNull($company->updated_by);
        $this->assertTrue($company->creator->is($creator));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'slug' => 'acme-corp',
            'status' => 'pending',
            'database_name' => 'workspace_'.strtolower($company->id),
        ], 'platform');
    }

    public function test_database_name_always_corresponds_to_company_id(): void
    {
        $creator = PlatformUser::factory()->create();

        $company = app(CompanyService::class)->create([
            'name' => 'Beta LLC',
            'slug' => 'beta',
        ], $creator);

        $this->assertSame(
            'workspace_'.strtolower($company->id),
            $company->database_name
        );
    }

    public function test_caller_supplied_system_fields_are_rejected(): void
    {
        $creator = PlatformUser::factory()->create();
        $service = app(CompanyService::class);

        foreach (['id', 'database_name', 'status', 'created_by', 'updated_by', 'deleted_at'] as $field) {
            try {
                $service->create([
                    'name' => 'Acme',
                    'slug' => 'acme-'.$field,
                    $field => 'attacker-value',
                ], $creator);

                $this->fail("Expected ValidationException when supplying [{$field}].");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertSame(0, Company::query()->count());
    }

    public function test_duplicate_slug_including_soft_deleted_is_rejected_by_validation(): void
    {
        $creator = PlatformUser::factory()->create();
        $existing = Company::factory()->create(['slug' => 'acme']);
        $existing->delete();

        $this->expectException(ValidationException::class);

        app(CompanyService::class)->create([
            'name' => 'Acme Again',
            'slug' => 'acme',
        ], $creator);
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $creator = PlatformUser::factory()->create();

        $this->expectException(ValidationException::class);

        app(CompanyService::class)->create([
            'name' => 'Auth Co',
            'slug' => 'auth',
        ], $creator);
    }

    public function test_slug_length_bounds(): void
    {
        $creator = PlatformUser::factory()->create();
        $service = app(CompanyService::class);

        try {
            $service->create(['name' => 'A', 'slug' => 'a'], $creator);
            $this->fail('Expected ValidationException for slug shorter than 2.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        $company = $service->create(['name' => 'OK', 'slug' => 'ab'], $creator);
        $this->assertSame('ab', $company->slug);

        try {
            $service->create([
                'name' => 'Too Long',
                'slug' => str_repeat('a', 64),
            ], $creator);
            $this->fail('Expected ValidationException for slug longer than 63.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }
    }

    public function test_invalid_email_is_rejected(): void
    {
        $creator = PlatformUser::factory()->create();

        $this->expectException(ValidationException::class);

        app(CompanyService::class)->create([
            'name' => 'Acme',
            'slug' => 'acme-email',
            'email' => 'not-an-email',
        ], $creator);
    }

    public function test_invalid_country_code_is_rejected(): void
    {
        $creator = PlatformUser::factory()->create();
        $service = app(CompanyService::class);

        try {
            $service->create([
                'name' => 'Acme',
                'slug' => 'acme-cc-1',
                'country_code' => 'IND',
            ], $creator);
            $this->fail('Expected ValidationException for country_code length.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('country_code', $exception->errors());
        }

        try {
            $service->create([
                'name' => 'Acme',
                'slug' => 'acme-cc-2',
                'country_code' => '1N',
            ], $creator);
            $this->fail('Expected ValidationException for non-letter country_code.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('country_code', $exception->errors());
        }
    }

    public function test_service_uses_platform_transaction(): void
    {
        $creator = PlatformUser::factory()->create();
        $levelsSeen = [];

        Company::creating(function () use (&$levelsSeen): void {
            $levelsSeen[] = DB::connection('platform')->transactionLevel();
        });

        $company = app(CompanyService::class)->create([
            'name' => 'Txn Co',
            'slug' => 'txn-co',
        ], $creator);

        $this->assertNotEmpty($levelsSeen);
        $this->assertGreaterThanOrEqual(1, max($levelsSeen));
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'slug' => 'txn-co',
        ], 'platform');
    }

    public function test_failed_create_does_not_leave_partial_row(): void
    {
        $creator = PlatformUser::factory()->create();

        try {
            app(CompanyService::class)->create([
                'name' => 'No Persist',
                'slug' => 'admin',
            ], $creator);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseMissing('companies', [
            'slug' => 'admin',
        ], 'platform');
    }
}
