<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\EvaluationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\StoreMonthlyEvaluationRequest;
use App\Models\User;
use App\Services\Evaluations\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TL / Manager monthly evaluation (T-3.3) — 5 bars from daily + monthly text.
 */
class EvaluationController extends Controller
{
    public function __construct(private readonly EvaluationService $evaluations) {}

    public function index(Request $request): View
    {
        $month = (int) ($request->input('month') ?: now()->month);
        $year = (int) ($request->input('year') ?: now()->year);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $actor = $request->user();
        $employees = $this->evaluations->evaluableEmployees($actor);

        $rows = $employees->map(function (User $employee) use ($actor, $month, $year) {
            $snapshot = $this->evaluations->snapshot($employee, $month, $year, $actor);

            return [
                'employee' => $employee,
                'scores' => $snapshot['scores'],
                'saved' => $snapshot['evaluation'] !== null,
            ];
        });

        return view('team-leader.evaluations.index', [
            'month' => $month,
            'year' => $year,
            'rows' => $rows,
        ]);
    }

    public function show(Request $request, User $employee): View
    {
        abort_unless($this->evaluations->actorMayEvaluate($request->user(), $employee), 403);

        $month = (int) ($request->input('month') ?: now()->month);
        $year = (int) ($request->input('year') ?: now()->year);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $snapshot = $this->evaluations->snapshot($employee, $month, $year, $request->user());

        return view('team-leader.evaluations.show', [
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'scores' => $snapshot['scores'],
            'evaluationText' => old('evaluation_text', $snapshot['evaluation_text']),
            'saved' => $snapshot['evaluation'] !== null,
            'otherEvaluations' => $snapshot['others'],
        ]);
    }

    public function store(StoreMonthlyEvaluationRequest $request, User $employee): RedirectResponse
    {
        abort_unless($this->evaluations->actorMayEvaluate($request->user(), $employee), 403);

        $data = $request->validated();

        try {
            $this->evaluations->saveMonthly(
                $request->user(),
                $employee,
                (int) $data['month'],
                (int) $data['year'],
                $data['evaluation_text'] ?? null,
            );
        } catch (EvaluationException $e) {
            throw ValidationException::withMessages([
                'evaluation_text' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.evaluations.show', [
                'employee' => $employee,
                'month' => $data['month'],
                'year' => $data['year'],
            ])
            ->with('success', __('evaluation.flash.monthly_saved'));
    }
}
