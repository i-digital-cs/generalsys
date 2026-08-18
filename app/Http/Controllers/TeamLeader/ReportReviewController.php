<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\DailyReportException;
use App\Exceptions\DailyScoreException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\StoreDailyScoresRequest;
use App\Http\Requests\TeamLeader\StoreReportResponseRequest;
use App\Models\Attendance;
use App\Services\Attendance\DailyReportService;
use App\Services\Evaluations\DailyScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TL / Manager daily-report review (T-2.6) + daily scores (T-3.1).
 */
class ReportReviewController extends Controller
{
    public function __construct(
        private readonly DailyReportService $reports,
        private readonly DailyScoreService $scores,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'all';

        if (! in_array($filter, ['pending', 'replied', 'all'], true)) {
            $filter = 'all';
        }

        $employeeId = $request->filled('employee_id')
            ? (int) $request->input('employee_id')
            : null;

        $actor = $request->user();

        return view('team-leader.reports.index', [
            'filter' => $filter,
            'employeeId' => $employeeId,
            'employees' => $this->reports->filterableUsers($actor),
            'reports' => $this->reports->teamReportsForActor($actor, $filter, $employeeId),
        ]);
    }

    public function show(Request $request, Attendance $attendance): View
    {
        $attendance->loadMissing(['user', 'photos']);

        abort_unless($this->reports->actorMayView($request->user(), $attendance), 403);

        $main = $this->reports->mainAttendanceForDate(
            $attendance->user,
            $attendance->date->toDateString(),
        );

        abort_if($main === null || $main->id !== $attendance->id, 404);
        abort_if(blank($main->daily_report), 404);

        $main->loadMissing('photos');

        return view('team-leader.reports.show', [
            'attendance' => $main,
            'isLocked' => $this->reports->isLocked($main),
            'canRespond' => $this->reports->actorMayRespond($request->user(), $main),
        ]);
    }

    public function respond(StoreReportResponseRequest $request, Attendance $attendance): RedirectResponse
    {
        $attendance->loadMissing('user');

        abort_unless($this->reports->actorMayRespond($request->user(), $attendance), 403);

        try {
            $this->reports->respond(
                $request->user(),
                $attendance,
                $request->validated('team_leader_response'),
            );
        } catch (DailyReportException $e) {
            throw ValidationException::withMessages([
                'team_leader_response' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.reports.show', $attendance)
            ->with('success', __('report.tl.flash.replied'));
    }

    public function scores(StoreDailyScoresRequest $request, Attendance $attendance): RedirectResponse
    {
        $attendance->loadMissing('user');

        abort_unless($this->scores->actorMayScore($request->user(), $attendance), 403);

        $main = $this->reports->mainAttendanceForDate(
            $attendance->user,
            $attendance->date->toDateString(),
        );

        abort_if($main === null || $main->id !== $attendance->id, 404);
        abort_if(blank($main->daily_report), 404);

        try {
            $this->scores->save($request->user(), $main, $request->validated());
        } catch (DailyScoreException $e) {
            throw ValidationException::withMessages([
                'performance_score' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.reports.show', $main)
            ->with('success', __('evaluation.flash.scores_saved'));
    }
}
