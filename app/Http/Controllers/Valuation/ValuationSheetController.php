<?php

namespace App\Http\Controllers\Valuation;

use App\Exceptions\ValuationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Valuation\StoreValuationSheetRequest;
use App\Models\ValuationAssignment;
use App\Models\ValuationSheet;
use App\Services\Valuation\ValuationExportService;
use App\Services\Valuation\ValuationSheetService;
use App\Services\Valuation\ValuationStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ValuationSheetController extends Controller
{
    public function __construct(
        private readonly ValuationSheetService $sheets,
        private readonly ValuationExportService $exports,
        private readonly ValuationStatsService $stats,
    ) {}

    public function index(Request $request, string $module): View
    {
        $this->guardModule($module);
        $user = $request->user();

        if (! $user->isManager() && ! $user->valuationAssignments()->where('module', $module)->exists()) {
            return view('valuation.unassigned', ['module' => $module]);
        }

        $this->authorize('viewAny', ValuationSheet::class);

        return view('valuation.sheets.index', [
            'module' => $module,
            'sheets' => $this->sheets->paginateFor($user, $module),
            'canFill' => $user->valuationAssignments()->where('module', $module)->exists(),
            'stats' => $this->stats->monthSnapshot($user, $module),
        ]);
    }

    public function form(Request $request, string $module): View
    {
        $this->guardModule($module);
        $user = $request->user();
        $date = $request->query('date', now()->toDateString());

        try {
            $this->sheets->assertMayAccessModule($user, $module);
        } catch (ValuationException $e) {
            abort(403, __($e->getMessage()));
        }

        $this->authorize('create', ValuationSheet::class);

        $sheet = $this->sheets->findForForm($user, $module, $date);
        $input = old('input', $sheet->input);

        return view('valuation.sheets.form', [
            'module' => $module,
            'sheet' => $sheet,
            'date' => $date,
            'input' => is_array($input) ? $input : $sheet->input,
            'result' => $this->sheets->preview(is_array($input) ? $input : $sheet->input),
        ]);
    }

    public function store(StoreValuationSheetRequest $request, string $module): RedirectResponse
    {
        $this->guardModule($module);
        $user = $request->user();
        $data = $request->validated();

        try {
            $sheet = $this->sheets->save(
                $user,
                $user,
                $module,
                $data['date'],
                $data['input'],
                $data['status'],
            );
        } catch (ValuationException $e) {
            abort(403, __($e->getMessage()));
        }

        return redirect()
            ->route('valuation.sheets.show', ['module' => $module, 'sheet' => $sheet])
            ->with('success', __('valuation.flash.saved'));
    }

    public function show(Request $request, string $module, ValuationSheet $sheet): View
    {
        $this->guardModule($module);
        abort_unless($sheet->module === $module, 404);
        $this->authorize('view', $sheet);

        return view('valuation.sheets.show', [
            'module' => $module,
            'sheet' => $sheet->load('user'),
        ]);
    }

    public function exportPdf(string $module, ValuationSheet $sheet): Response
    {
        $this->guardModule($module);
        abort_unless($sheet->module === $module, 404);
        $this->authorize('view', $sheet);

        $filename = sprintf(
            'valuation-%s-%s-%d.pdf',
            $module,
            $sheet->date->toDateString(),
            $sheet->id,
        );

        return $this->exports->downloadPdf($sheet)->download($filename);
    }

    private function guardModule(string $module): void
    {
        abort_unless(in_array($module, ValuationAssignment::MODULES, true), 404);
    }
}
