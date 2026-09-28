<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'name' => fake()->company(),
            'slug' => fake()->unique()->regexify('[a-z0-9]{2,12}'),
            'database_name' => 'workspace_'.strtolower($id),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'secondary_email' => null,
            'phone' => fake()->numerify('98########'),
            'gst_number' => null,
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country_code' => 'IN',
            'postal_code' => fake()->postcode(),
            'status' => CompanyStatus::Pending,
            'created_by' => PlatformUser::factory(),
            'updated_by' => null,
        ];
    }
}
