<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\DriverMediaHistoryRequest;
use App\Services\Attendance\DriverMediaHistoryService;
use Illuminate\View\View;

/**
 * Driver media history — own punch + field photos with filters.
 */
class DriverMediaController extends Controller
{
    public function __construct(
        private readonly DriverMediaHistoryService $media,
    ) {}

    public function index(DriverMediaHistoryRequest $request): View
    {
        $filters = $request->validated();

        return view('employee.media.index', [
            'photos' => $this->media->paginate($request->user(), $filters),
            'from' => $filters['from'],
            'to' => $filters['to'],
            'kind' => $filters['kind'],
            'location' => $filters['location'],
            'q' => $filters['q'] ?? '',
        ]);
    }
}
