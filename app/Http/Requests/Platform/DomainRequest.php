<?php

namespace App\Http\Requests\Platform;

use App\Enums\DomainType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Boundary validation for adding or changing a Company's single effective domain (F4, F5).
 *
 * Owns only HTTP-shape validation: a non-empty `domain` (hostname for a custom domain, or the
 * label for a subdomain) and a `type` that must be a valid DomainType value. Hostname
 * normalization, uniqueness, and the single-primary invariant are enforced server-side by the
 * Domain Actions, not here. Authorization lives in the controller (CompanyPolicy::manageDomains).
 */
class DomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DomainType::class)],
        ];
    }

    /**
     * The validated DomainType for this request.
     */
    public function domainType(): DomainType
    {
        return DomainType::from($this->string('type')->toString());
    }
}
