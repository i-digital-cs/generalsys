<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Gross\SalesPersonStatsService;
use App\Support\GrossAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesPersonStatsController extends Controller
{
    public function __construct(
        private readonly SalesPersonStatsService $stats,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(GrossAccess::maySell($request->user()), 403);

        $summary = $this->stats->summarize($request->user(), $request->query());

        return view('gross.sales.stats.index', [
            'filters' => $summary['filters'],
            'rows' => $summary['rows'],
            'totals' => $summary['totals'],
            'salesPeople' => $this->stats->salesPersonFilterOptions($request->user()),
            'canFilterSalesPerson' => $this->stats->mayViewAll($request->user()),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $detail = $this->stats->forUser($request->user(), $user, $request->query());

        return view('gross.sales.stats.show', [
            'filters' => $detail['filters'],
            'name' => $detail['name'],
            'isUnassigned' => false,
            'kpis' => $detail['kpis'],
            'invoices' => $detail['invoices'],
            'subject' => $detail['subject'],
        ]);
    }

    public function unassigned(Request $request): View
    {
        $detail = $this->stats->forUser($request->user(), null, $request->query(), true);

        return view('gross.sales.stats.show', [
            'filters' => $detail['filters'],
            'name' => $detail['name'],
            'isUnassigned' => true,
            'kpis' => $detail['kpis'],
            'invoices' => $detail['invoices'],
            'subject' => null,
        ]);
    }
}
