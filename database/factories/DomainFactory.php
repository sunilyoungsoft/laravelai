<?php

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Company;
use App\Models\Domain;
use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'company_id' => Company::factory(),
            'domain' => Str::lower(fake()->unique()->domainName()),
            'type' => DomainType::Custom,
            'is_primary' => false,
            'status' => DomainStatus::Pending,
            'verified_at' => null,
            'created_by' => PlatformUser::factory(),
            'updated_by' => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => DomainStatus::Active]);
    }

    public function subdomain(): static
    {
        return $this->state(fn () => ['type' => DomainType::Subdomain]);
    }
}
