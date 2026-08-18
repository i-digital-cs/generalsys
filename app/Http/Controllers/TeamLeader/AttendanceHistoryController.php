<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\AttendanceHistoryRequest;
use App\Services\Attendance\AttendanceHistoryService;
use Illuminate\View\View;

/**
 * Ops attendance history: person, dates, absent, photos, GPS (T-7.6).
 */
class AttendanceHistoryController extends Controller
{
    public function __construct(
        private readonly AttendanceHistoryService $history,
    ) {}

    public function index(AttendanceHistoryRequest $request): View
    {
        $filters = $request->validated();
        $days = $this->history->paginate($request->user(), array_merge($filters, [
            'page' => max(1, (int) $request->integer('page', 1)),
            'path' => $request->url(),
        ]));

        return view('team-leader.attendance.index', [
            'days' => $days,
            'employees' => $this->history->filterableUsers($request->user()),
            'employeeId' => $filters['employee_id'] ?? null,
            'from' => $filters['from'],
            'to' => $filters['to'],
            'kind' => $filters['kind'],
            'evidence' => $filters['evidence'],
        ]);
    }
}
