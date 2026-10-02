<?php

namespace App\Http\Controllers\Platform;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Render the Platform dashboard with Company counts grouped by status.
     *
     * Counts run on the explicit `platform` connection (the Company model
     * defaults to it) and exclude soft-deleted Companies — the default
     * SoftDeletes query already omits trashed rows, so no withTrashed.
     */
    public function __invoke(): Response
    {
        $this->authorize('viewAny', Company::class);

        $counts = $this->companyStatusCounts();

        return Inertia::render('Platform/Dashboard', [
            'statusCounts' => $counts,
            'totalCompanies' => array_sum($counts),
        ]);
    }

    /**
     * A stable map of every status => count. Statuses with no Companies
     * report zero so the dashboard cards stay stable.
     *
     * @return array<string, int>
     */
    private function companyStatusCounts(): array
    {
        $tallies = Company::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (CompanyStatus::cases() as $status) {
            $counts[$status->value] = (int) $tallies->get($status->value, 0);
        }

        return $counts;
    }
}
