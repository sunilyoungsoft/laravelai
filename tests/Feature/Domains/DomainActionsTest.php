<?php

namespace Tests\Feature\Domains;

use App\Actions\Platform\Domains\ActivateSubdomainAction;
use App\Actions\Platform\Domains\CreateDomainAction;
use App\Actions\Platform\Domains\CreateDomainData;
use App\Actions\Platform\Domains\SetPrimaryDomainAction;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class DomainActionsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): PlatformUser
    {
        return PlatformUser::factory()->create();
    }

    private function company(): Company
    {
        return Company::factory()->create();
    }

    public function test_create_custom_domain_normalizes_and_defaults_to_pending(): void
    {
        $company = $this->company();
        $actor = $this->actor();

        $domain = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, '  ACME.Example.com. ', DomainType::Custom),
            $actor
        );

        $this->assertSame('acme.example.com', $domain->domain);
        $this->assertSame(DomainType::Custom, $domain->type);
        $this->assertSame(DomainStatus::Pending, $domain->status);
        $this->assertFalse($domain->is_primary);
        $this->assertSame($actor->id, $domain->created_by);
    }

    public function test_create_rejects_invalid_hostname(): void
    {
        $company = $this->company();

        $this->expectException(InvalidArgumentException::class);

        app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'https://acme.example.com/app', DomainType::Custom),
            $this->actor()
        );
    }

    public function test_domain_uniqueness_is_global(): void
    {
        $actor = $this->actor();
        $a = $this->company();
        $b = $this->company();

        app(CreateDomainAction::class)->execute(
            new CreateDomainData($a->id, 'shared.example.com', DomainType::Custom),
            $actor
        );

        $this->expectException(ValidationException::class);
        app(CreateDomainAction::class)->execute(
            new CreateDomainData($b->id, 'shared.example.com', DomainType::Custom),
            $actor
        );
    }

    public function test_soft_deleted_domain_identity_is_reserved_and_reused_by_restore(): void
    {
        $actor = $this->actor();
        $a = $this->company();
        $b = $this->company();

        $first = app(CreateDomainAction::class)->execute(
            new CreateDomainData($a->id, 'reserved.example.com', DomainType::Custom),
            $actor
        );
        $firstId = $first->id;
        $first->delete();

        // Re-creating the same hostname restores the existing record (no duplicate row).
        $again = app(CreateDomainAction::class)->execute(
            new CreateDomainData($b->id, 'reserved.example.com', DomainType::Custom),
            $actor
        );

        $this->assertSame($firstId, $again->id, 'Reuse should restore the reserved identity.');
        $this->assertSame($b->id, $again->company_id);
        $this->assertSame(1, Domain::withTrashed()->where('domain', 'reserved.example.com')->count());
    }

    public function test_company_can_have_multiple_domains(): void
    {
        $company = $this->company();
        $actor = $this->actor();

        app(CreateDomainAction::class)->execute(new CreateDomainData($company->id, 'one.example.com', DomainType::Custom), $actor);
        app(CreateDomainAction::class)->execute(new CreateDomainData($company->id, 'two.example.com', DomainType::Custom), $actor);

        $this->assertSame(2, Domain::where('company_id', $company->id)->count());
    }

    public function test_only_one_primary_domain_per_company(): void
    {
        $company = $this->company();
        $actor = $this->actor();

        $first = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'first.example.com', DomainType::Custom, isPrimary: true),
            $actor
        );
        $second = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'second.example.com', DomainType::Custom, isPrimary: true),
            $actor
        );

        $this->assertSame(1, Domain::where('company_id', $company->id)->where('is_primary', true)->count());
        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
    }

    public function test_set_primary_swaps_atomically(): void
    {
        $company = $this->company();
        $actor = $this->actor();

        $first = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'p1.example.com', DomainType::Custom, isPrimary: true),
            $actor
        );
        $second = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'p2.example.com', DomainType::Custom),
            $actor
        );

        app(SetPrimaryDomainAction::class)->execute($second, $actor);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
        $this->assertSame(1, Domain::where('company_id', $company->id)->where('is_primary', true)->count());
    }

    public function test_subdomain_uses_configured_base_domain(): void
    {
        config(['tenancy.base_domain' => 'platform-base.test']);
        $company = $this->company();

        $domain = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'acme', DomainType::Subdomain),
            $this->actor()
        );

        $this->assertSame('acme.platform-base.test', $domain->domain);
        $this->assertSame(DomainType::Subdomain, $domain->type);
        $this->assertSame(DomainStatus::Pending, $domain->status);
    }

    public function test_subdomain_rejects_reserved_label(): void
    {
        config(['tenancy.base_domain' => 'platform-base.test']);
        $company = $this->company();

        $this->expectException(ValidationException::class);
        app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'admin', DomainType::Subdomain),
            $this->actor()
        );
    }

    public function test_subdomain_requires_configured_base_domain(): void
    {
        config(['tenancy.base_domain' => null]);
        $company = $this->company();

        $this->expectException(ValidationException::class);
        app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'acme', DomainType::Subdomain),
            $this->actor()
        );
    }

    public function test_subdomain_can_be_activated_but_custom_cannot(): void
    {
        config(['tenancy.base_domain' => 'platform-base.test']);
        $company = $this->company();
        $actor = $this->actor();

        $subdomain = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'acme', DomainType::Subdomain),
            $actor
        );
        $activated = app(ActivateSubdomainAction::class)->execute($subdomain, $actor);

        $this->assertSame(DomainStatus::Active, $activated->status);
        $this->assertNotNull($activated->verified_at);

        $custom = app(CreateDomainAction::class)->execute(
            new CreateDomainData($company->id, 'app.acme.com', DomainType::Custom),
            $actor
        );

        $this->expectException(InvalidArgumentException::class);
        app(ActivateSubdomainAction::class)->execute($custom, $actor);
    }

    public function test_domain_uses_platform_connection(): void
    {
        $this->assertSame('platform', (new Domain)->getConnectionName());
    }
}
