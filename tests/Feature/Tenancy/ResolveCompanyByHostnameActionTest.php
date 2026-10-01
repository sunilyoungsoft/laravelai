<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Platform\ResolveCompanyByHostnameAction;
use App\Enums\CompanyStatus;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Exceptions\Tenancy\UnresolvableDomainException;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveCompanyByHostnameActionTest extends TestCase
{
    use RefreshDatabase;

    private function activeDomainFor(Company $company, string $host, DomainType $type = DomainType::Custom): Domain
    {
        return Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => $host,
            'type' => $type,
            'status' => DomainStatus::Active,
            'created_by' => PlatformUser::factory(),
        ]);
    }

    private function resolver(): ResolveCompanyByHostnameAction
    {
        return app(ResolveCompanyByHostnameAction::class);
    }

    public function test_resolves_hostname_to_correct_company(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->activeDomainFor($company, 'acme.example.com');

        $resolved = $this->resolver()->execute('ACME.Example.com.');

        $this->assertSame($company->id, $resolved->id);
    }

    public function test_unknown_hostname_fails_safely(): void
    {
        $this->expectException(UnresolvableDomainException::class);
        $this->resolver()->execute('nobody.example.com');
    }

    public function test_domain_of_company_a_never_resolves_company_b(): void
    {
        $a = Company::factory()->create(['status' => CompanyStatus::Active]);
        $b = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->activeDomainFor($a, 'a.example.com');
        $this->activeDomainFor($b, 'b.example.com');

        $this->assertSame($a->id, $this->resolver()->execute('a.example.com')->id);
        $this->assertSame($b->id, $this->resolver()->execute('b.example.com')->id);
        $this->assertNotSame($a->id, $this->resolver()->execute('b.example.com')->id);
    }

    public function test_inactive_domain_does_not_resolve(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        Domain::factory()->create([
            'company_id' => $company->id,
            'domain' => 'pending.example.com',
            'status' => DomainStatus::Pending,
            'created_by' => PlatformUser::factory(),
        ]);

        $this->expectException(UnresolvableDomainException::class);
        $this->resolver()->execute('pending.example.com');
    }

    public function test_non_active_company_does_not_resolve(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Suspended]);
        $this->activeDomainFor($company, 'suspended.example.com');

        $this->expectException(UnresolvableDomainException::class);
        $this->resolver()->execute('suspended.example.com');
    }

    public function test_resolver_does_not_initialize_tenancy(): void
    {
        $company = Company::factory()->create(['status' => CompanyStatus::Active]);
        $this->activeDomainFor($company, 'noinit.example.com');

        $this->resolver()->execute('noinit.example.com');

        $this->assertFalse(tenancy()->initialized, 'Resolver must not initialize tenancy.');
    }
}
