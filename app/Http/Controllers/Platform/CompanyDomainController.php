<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\Domains\CreateDomainAction;
use App\Actions\Platform\Domains\CreateDomainData;
use App\Actions\Platform\Domains\SetPrimaryDomainAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\DomainRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Manage a Company's single effective (primary) domain from the Platform admin UI (F4, F5, 1E-C).
 *
 * Thin controller: it authorizes `manageDomains` on the specific Company, then delegates ALL
 * domain logic (hostname normalization, uniqueness, the single-primary invariant) to the existing
 * Domain Actions. It never touches the `domains` table directly and holds no domain rules itself.
 *
 * The UI exposes exactly one effective domain per company (1E-C), so there are only two
 * operations:
 *  - store  — ADD the first/effective domain when the company has none.
 *  - update — CHANGE the effective domain when one already exists.
 *
 * CreateDomainAction throws ValidationException keyed to `domain` on a reserved label, a bad
 * hostname, or a uniqueness conflict. We let it bubble so Inertia maps the message back onto the
 * `domain` field (F5 "surface validation errors").
 */
class CompanyDomainController extends Controller
{
    /**
     * Add the single effective domain for a Company that has none (F4, F5).
     *
     * The first domain IS the effective domain, so it is created as primary. CreateDomainAction
     * creates it `pending` and makes it primary atomically.
     */
    public function store(DomainRequest $request, Company $company, CreateDomainAction $createDomain): RedirectResponse
    {
        $this->authorize('manageDomains', $company);

        try {
            $createDomain->execute(
                new CreateDomainData(
                    companyId: $company->id,
                    domain: $request->string('domain')->toString(),
                    type: $request->domainType(),
                    isPrimary: true,
                ),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw $this->domainValidationError($exception);
        }

        return redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Domain added.');
    }

    /**
     * Change the effective domain for a Company that already has one (F5, 1E-C).
     *
     * Per design.md: create the replacement via CreateDomainAction (which normalizes, enforces
     * uniqueness, and restores a reserved hostname), then promote it with SetPrimaryDomainAction
     * so the single-primary swap (demote old, promote new) is applied atomically server-side.
     */
    public function update(DomainRequest $request, Company $company, CreateDomainAction $createDomain, SetPrimaryDomainAction $setPrimaryDomain): RedirectResponse
    {
        $this->authorize('manageDomains', $company);

        try {
            $replacement = $createDomain->execute(
                new CreateDomainData(
                    companyId: $company->id,
                    domain: $request->string('domain')->toString(),
                    type: $request->domainType(),
                    isPrimary: false,
                ),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw $this->domainValidationError($exception);
        }

        $setPrimaryDomain->execute($replacement, $request->user());

        return redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Domain changed.');
    }

    /**
     * Turn a malformed-hostname rejection from the Hostname value object into a `domain` field
     * validation error (F5). CreateDomainAction already converts reserved labels and uniqueness
     * conflicts into ValidationException keyed to `domain`; a syntactically invalid custom
     * hostname throws InvalidArgumentException, which is user input — not a server fault — so the
     * web entry point maps it onto the same field instead of surfacing a 500.
     */
    private function domainValidationError(InvalidArgumentException $exception): ValidationException
    {
        return ValidationException::withMessages([
            'domain' => [$exception->getMessage()],
        ]);
    }
}
