<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\ShiftAssignmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\StoreShiftAssignmentRequest;
use App\Http\Requests\TeamLeader\UpdateShiftAssignmentRequest;
use App\Models\ShiftAssignment;
use App\Services\Attendance\ShiftAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TL / Manager shift roster (T-2.14) — thin: authorize → service → view.
 */
class ShiftAssignmentController extends Controller
{
    public function __construct(private readonly ShiftAssignmentService $shifts) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ShiftAssignment::class);

        $user = $request->user();
        $date = $request->string('date')->toString() ?: now()->toDateString();
        $preselectUserId = $request->integer('user_id') ?: null;

        return view('team-leader.shifts.index', [
            'assignments' => $this->shifts->listForActor($user, $date),
            'employees' => $this->shifts->assignableEmployees($user),
            'teamRoster' => $this->shifts->teamRosterForDate($user, $date),
            'date' => $date,
            'preselectUserId' => $preselectUserId,
            'shiftTypes' => [
                ...ShiftAssignment::BASE_TYPES,
                ...ShiftAssignment::PREMIUM_TYPES,
            ],
        ]);
    }

    public function store(StoreShiftAssignmentRequest $request): RedirectResponse
    {
        try {
            $this->shifts->assign($request->user(), $request->validated());
        } catch (ShiftAssignmentException $e) {
            throw ValidationException::withMessages(['shift_type' => __($e->getMessage())]);
        }

        return redirect()
            ->route('team-leader.shifts', ['date' => $request->validated('date')])
            ->with('success', __('shifts.flash.created'));
    }

    public function edit(ShiftAssignment $shiftAssignment): View
    {
        $shiftAssignment->loadMissing('user');
        $this->authorize('update', $shiftAssignment);

        abort_unless($shiftAssignment->status === ShiftAssignment::STATUS_SCHEDULED, 404);

        $actor = request()->user();

        return view('team-leader.shifts.edit', [
            'assignment' => $shiftAssignment,
            'employees' => $this->shifts->assignableEmployees($actor),
            'shiftTypes' => [
                ...ShiftAssignment::BASE_TYPES,
                ...ShiftAssignment::PREMIUM_TYPES,
            ],
        ]);
    }

    public function update(UpdateShiftAssignmentRequest $request, ShiftAssignment $shiftAssignment): RedirectResponse
    {
        $shiftAssignment->loadMissing('user');

        try {
            $updated = $this->shifts->update($request->user(), $shiftAssignment, $request->validated());
        } catch (ShiftAssignmentException $e) {
            throw ValidationException::withMessages(['shift_type' => __($e->getMessage())]);
        }

        return redirect()
            ->route('team-leader.shifts', ['date' => $updated->date->toDateString()])
            ->with('success', __('shifts.flash.updated'));
    }

    public function cancel(Request $request, ShiftAssignment $shiftAssignment): RedirectResponse
    {
        $shiftAssignment->loadMissing('user');
        $this->authorize('cancel', $shiftAssignment);

        try {
            $this->shifts->cancel($shiftAssignment);
        } catch (ShiftAssignmentException $e) {
            throw ValidationException::withMessages(['assignment' => __($e->getMessage())]);
        }

        return redirect()
            ->route('team-leader.shifts', ['date' => $request->input('date', $shiftAssignment->date->toDateString())])
            ->with('success', __('shifts.flash.cancelled'));
    }
}
