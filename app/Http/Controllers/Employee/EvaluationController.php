<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Evaluations\EvaluationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Evaluation (T-3.4) — read-only monthly averages + TL text.
 */
class EvaluationController extends Controller
{
    public function __construct(private readonly EvaluationService $evaluations) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $month = (int) ($request->input('month') ?: now()->month);
        $year = (int) ($request->input('year') ?: now()->year);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $evaluation = $this->evaluations->findForUserMonth($user, $month, $year);
        $history = $this->evaluations->listForUser($user);

        return view('employee.evaluation', [
            'month' => $month,
            'year' => $year,
            'evaluation' => $evaluation,
            'history' => $history,
        ]);
    }
}
