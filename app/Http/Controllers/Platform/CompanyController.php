<?php

namespace App\Http\Controllers\Platform;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Domain;
use App\Services\Platform\CompanyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * List Companies with free-text search and a status filter.
     *
     * Query runs on the explicit `platform` connection (the Company model defaults
     * to it) and excludes soft-deleted Companies — the default SoftDeletes scope
     * already omits trashed rows, so no withTrashed. The current search/status
     * filters are reflected back in the URL via withQueryString so pages stay
     * shareable (D2).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = $this->sanitizeSearch($request->query('search'));
        $status = $this->resolveStatusFilter($request->query('status'));

        $companies = Company::query()
            ->when($search !== null, fn (Builder $query) => $this->applySearch($query, $search))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Resolve every effective domain for the page in one query to avoid N+1.
        $effectiveDomains = $this->effectiveDomainsFor($companies->getCollection()->modelKeys());

        $companies->through(fn (Company $company) => $this->toListItem($company, $effectiveDomains));

        return Inertia::render('Platform/Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
            ],
        ]);
    }

    /**
     * Render the Create Company form (E1).
     *
     * The React form (task 5.4) captures only the Company profile fields that
     * CompanyService::create accepts; system fields are never exposed.
     */
    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('Platform/Companies/Create');
    }

    /**
     * Create a Platform Company record only — no Workspace provisioning (E2).
     *
     * The controller stays thin: it collects the profile input and delegates all
     * creation logic to CompanyService::create, which validates/normalizes, rejects
     * system fields, generates id/database_name, sets status=Pending, and persists
     * in a transaction. CompanyService throws ValidationException on bad input; we
     * let it bubble so Inertia maps the errors back onto the form fields (E3).
     */
    public function store(Request $request, CompanyService $companies): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $company = $companies->create(
            $request->only((new Company)->getFillable()),
            $request->user(),
        );

        return redirect()
            ->route('platform.companies.show', $company)
            ->with('success', 'Company created.');
    }

    /**
     * Show a single Company's Details page (F1).
     *
     * The {company} binding is resolved on the `platform` connection (the Company
     * model defaults to it). We authorize `view` on this specific Company first,
     * then expose a safe Company shape, the single effective (primary) Domain (or
     * null), and a per-company `can` map (view/provision/manageDomains) resolved
     * for the acting user against THIS Company. That `can` map is distinct from the
     * global auth.can shared in HandleInertiaRequests and drives per-company button
     * gating on the page (B3) — the server still authorizes every action (B2).
     */
    public function show(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);

        $user = $request->user();

        return Inertia::render('Platform/Companies/Show', [
            'company' => $this->toDetail($company),
            'effectiveDomain' => $this->primaryDomainFor($company),
            'can' => [
                'view' => $user->can('view', $company),
                'provision' => $user->can('provision', $company),
                'manageDomains' => $user->can('manageDomains', $company),
            ],
        ]);
    }

    /**
     * Normalize the free-text search term; treat blank input as "no search".
     */
    private function sanitizeSearch(?string $search): ?string
    {
        $search = is_string($search) ? trim($search) : null;

        return $search === '' ? null : $search;
    }

    /**
     * Resolve the status query value to a CompanyStatus, ignoring invalid input.
     */
    private function resolveStatusFilter(?string $status): ?CompanyStatus
    {
        if (! is_string($status) || $status === '') {
            return null;
        }

        return CompanyStatus::tryFrom($status);
    }

    /**
     * Match the search term against the Company name and slug.
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        $term = '%'.$search.'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term);
        });
    }

    /**
     * Map a Company to the minimal, safe shape the list page consumes.
     *
     * @param  array<string, string>  $effectiveDomains  company id => primary hostname
     * @return array<string, mixed>
     */
    private function toListItem(Company $company, array $effectiveDomains): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => $company->status->value,
            'effective_domain' => $effectiveDomains[$company->id] ?? null,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }

    /**
     * Map a Company to the safe, detailed shape the Show page consumes.
     *
     * Exposes profile fields and metadata (status, timestamps) plus the Workspace
     * lifecycle inputs the Workspace card needs (F2): the CompanyStatus value and
     * whether a database_name has been assigned. We expose database_name presence
     * (a boolean), not the raw value, to keep the internal workspace DB name off
     * the wire.
     *
     * @return array<string, mixed>
     */
    private function toDetail(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => $company->status->value,
            'contact_person' => $company->contact_person,
            'email' => $company->email,
            'secondary_email' => $company->secondary_email,
            'phone' => $company->phone,
            'gst_number' => $company->gst_number,
            'address_line_1' => $company->address_line_1,
            'address_line_2' => $company->address_line_2,
            'city' => $company->city,
            'state' => $company->state,
            'country_code' => $company->country_code,
            'postal_code' => $company->postal_code,
            'has_workspace_database' => $company->database_name !== null,
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The single effective (primary) Domain for one Company, resolved on the
     * `platform` connection (the Domain model defaults to it). Returns a safe
     * shape or null when the Company has no primary domain yet (F4).
     *
     * @return array<string, mixed>|null
     */
    private function primaryDomainFor(Company $company): ?array
    {
        $domain = Domain::query()
            ->where('company_id', $company->id)
            ->where('is_primary', true)
            ->first();

        if ($domain === null) {
            return null;
        }

        return [
            'id' => $domain->id,
            'domain' => $domain->domain,
            'type' => $domain->type->value,
            'status' => $domain->status->value,
            'is_primary' => $domain->is_primary,
            'verified_at' => $domain->verified_at?->toIso8601String(),
        ];
    }

    /**
     * The effective (primary) domain hostname for each of the given Company ids,
     * resolved on the `platform` connection (the Domain model defaults to it).
     *
     * @param  array<int, string>  $companyIds
     * @return array<string, string> company id => primary hostname
     */
    private function effectiveDomainsFor(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return Domain::query()
            ->whereIn('company_id', $companyIds)
            ->where('is_primary', true)
            ->pluck('domain', 'company_id')
            ->all();
    }
}
