<?php

namespace App\Services\Platform;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    /**
     * Reserved public/platform slug values (simple in-service list).
     *
     * @var list<string>
     */
    private const RESERVED_SLUGS = [
        'www',
        'api',
        'admin',
        'platform',
        'app',
        'mail',
        'ftp',
        'localhost',
        'staging',
        'static',
        'assets',
        'cdn',
        'status',
        'health',
        'auth',
        'login',
        'billing',
        'support',
        'docs',
        'dashboard',
        'system',
        'root',
    ];

    /**
     * System-managed fields that callers must not supply.
     *
     * @var list<string>
     */
    private const SYSTEM_FIELDS = [
        'id',
        'database_name',
        'status',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    /**
     * Create a Platform Company record only (no Workspace database provisioning).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, PlatformUser $creator): Company
    {
        $this->rejectSystemFields($attributes);

        $validated = $this->validateAndNormalize($attributes);

        return DB::connection('platform')->transaction(function () use ($validated, $creator) {
            $id = (string) Str::ulid();

            $company = new Company;
            $company->setConnection('platform');
            $company->fill($validated);
            $company->id = $id;
            $company->database_name = 'workspace_'.strtolower($id);
            $company->status = CompanyStatus::Pending;
            $company->created_by = $creator->id;
            $company->updated_by = null;
            $company->save();

            return $company->fresh(['creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rejectSystemFields(array $attributes): void
    {
        $supplied = array_values(array_intersect(array_keys($attributes), self::SYSTEM_FIELDS));

        if ($supplied === []) {
            return;
        }

        $messages = [];

        foreach ($supplied as $field) {
            $messages[$field] = ["The {$field} field is application-controlled and must not be supplied."];
        }

        throw ValidationException::withMessages($messages);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validateAndNormalize(array $attributes): array
    {
        if (array_key_exists('name', $attributes) && is_string($attributes['name'])) {
            $attributes['name'] = trim($attributes['name']);
        }

        if (array_key_exists('slug', $attributes) && is_string($attributes['slug'])) {
            $attributes['slug'] = Str::lower(trim($attributes['slug']));
        }

        if (array_key_exists('country_code', $attributes) && is_string($attributes['country_code'])) {
            $attributes['country_code'] = Str::upper(trim($attributes['country_code']));

            if ($attributes['country_code'] === '') {
                $attributes['country_code'] = null;
            }
        }

        foreach (['email', 'secondary_email', 'phone', 'contact_person', 'gst_number', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code'] as $nullableString) {
            if (array_key_exists($nullableString, $attributes) && is_string($attributes[$nullableString])) {
                $attributes[$nullableString] = trim($attributes[$nullableString]);

                if ($attributes[$nullableString] === '') {
                    $attributes[$nullableString] = null;
                }
            }
        }

        // Connection-qualified table rule so soft-deleted rows remain in the unique check.
        // Do not use Rule::unique(Company::class) — that auto-applies withoutTrashed().
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
                'unique:platform.companies,slug',
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'secondary_email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'postal_code' => ['nullable', 'string', 'max:255'],
        ], [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.not_in' => 'The slug is reserved.',
            'country_code.regex' => 'The country code must be exactly 2 ASCII letters.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
