<?php

namespace App\Http\Middleware;

use App\Actions\Platform\ResolveCompanyByHostnameAction;
use App\Exceptions\Tenancy\UnresolvableDomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the request hostname to a Company (Platform data) and initialize Stancl tenancy.
 *
 * Boundary responsibilities (per OD-3):
 *  - Central domains (config('tenancy.central_domains')) pass through without tenancy.
 *  - Otherwise, resolve the Company via ResolveCompanyByHostnameAction (resolve-only) and
 *    initialize tenancy through Stancl (tenancy()->initialize()). No manual DB switching.
 *  - The Workspace is chosen solely from the resolved host — never from a client-supplied
 *    company/tenant/database identifier.
 *  - An unresolvable hostname fails safely (404); no tenancy is initialized and there is no
 *    Platform fallback for tenant data.
 */
class InitializeTenancyByResolvedDomain
{
    public function __construct(
        private readonly ResolveCompanyByHostnameAction $resolveCompany,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $hostname = $request->getHost();

        if ($this->isCentralDomain($hostname)) {
            return $next($request);
        }

        try {
            $company = $this->resolveCompany->execute($hostname);
        } catch (UnresolvableDomainException) {
            abort(404);
        }

        tenancy()->initialize($company);

        return $next($request);
    }

    private function isCentralDomain(string $hostname): bool
    {
        $central = (array) config('tenancy.central_domains', []);

        return in_array($hostname, $central, true);
    }
}
