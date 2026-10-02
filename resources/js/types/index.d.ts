export type WelcomeProps = {
    appName: string;
    laravelVersion: string;
    phpVersion: string;
};

/**
 * Company lifecycle status. String union mirrors App\Enums\CompanyStatus
 * (the `status` prop is always serialized to its backing string value).
 */
export type CompanyStatus =
    | 'pending'
    | 'provisioning'
    | 'active'
    | 'provisioning_failed'
    | 'suspended'
    | 'deactivated';

/** Domain kind. Mirrors App\Enums\DomainType. */
export type DomainType = 'subdomain' | 'custom';

/** Domain lifecycle status. Mirrors App\Enums\DomainStatus. */
export type DomainStatus = 'pending' | 'active' | 'inactive' | 'verification_failed';

/**
 * A Company row as returned by CompanyController@index (toListItem).
 * Minimal, safe shape consumed by the companies list.
 */
export type CompanyListItem = {
    id: string;
    name: string;
    slug: string;
    status: CompanyStatus;
    effective_domain: string | null;
    created_at: string | null;
};

/**
 * A Company's full detail shape as returned by CompanyController@show (toDetail).
 * System fields (e.g. the raw workspace database name) are never on the wire;
 * `has_workspace_database` exposes presence only.
 */
export type CompanyDetail = {
    id: string;
    name: string;
    slug: string;
    status: CompanyStatus;
    contact_person: string | null;
    email: string | null;
    secondary_email: string | null;
    phone: string | null;
    gst_number: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    state: string | null;
    country_code: string | null;
    postal_code: string | null;
    has_workspace_database: boolean;
    created_at: string | null;
    updated_at: string | null;
};

/**
 * The single effective (primary) Domain as returned by CompanyController@show
 * (primaryDomainFor), or null when the Company has no primary domain yet.
 */
export type Domain = {
    id: string;
    domain: string;
    type: DomainType;
    status: DomainStatus;
    is_primary: boolean;
    verified_at: string | null;
};

/**
 * Per-company ability map returned by CompanyController@show. UX gating only —
 * the server still authorizes every action.
 */
export type CompanyAbilities = {
    view: boolean;
    provision: boolean;
    manageDomains: boolean;
};

/**
 * A single link entry in Laravel's LengthAwarePaginator JSON `links` array.
 */
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * Laravel LengthAwarePaginator JSON, generic over the row type. Matches the
 * default Eloquent paginator serialization (data + meta + links).
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

/** The authenticated Platform User identity shared via Inertia (ULID id). */
export type AuthUser = {
    id: string;
    name: string;
    email: string;
};

/** Global, non-company-specific abilities shared via Inertia `auth.can`. */
export type AuthAbilities = {
    'companies.viewAny': boolean;
    'companies.create': boolean;
};

/** Shared `auth` prop from HandleInertiaRequests::share. */
export type Auth = {
    user: AuthUser | null;
    can: AuthAbilities;
};

/** Shared `flash` prop from HandleInertiaRequests::share. */
export type FlashProps = {
    success: string | null;
    error: string | null;
    demo_note_id?: string | null;
};

/**
 * Base props shared on every Inertia page by HandleInertiaRequests::share.
 * Extend this for individual page prop types.
 */
export type SharedProps = {
    appName: string;
    auth: Auth;
    flash: FlashProps;
};

/** Convenience alias: a page's own props merged with the shared props. */
export type PageProps<T = Record<string, unknown>> = T & SharedProps;
