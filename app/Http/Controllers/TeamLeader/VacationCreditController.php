<?php

namespace App\Http\Controllers\TeamLeader;

use App\Exceptions\VacationRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamLeader\StoreVacationCreditRequest;
use App\Models\User;
use App\Services\Vacations\VacationBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** TL / Manager vacation credit (no money). */
class VacationCreditController extends Controller
{
    public function __construct(private readonly VacationBalanceService $balances) {}

    public function index(Request $request): View
    {
        $year = (int) now()->format('Y');

        return view('team-leader.vacation-credit.index', [
            'employees' => $this->balances->manageableEmployees($request->user()),
            'year' => $year,
        ]);
    }

    public function store(StoreVacationCreditRequest $request, User $employee): RedirectResponse
    {
        abort_unless($this->balances->actorMayManage($request->user(), $employee), 403);

        try {
            $this->balances->addCredit(
                $request->user(),
                $employee,
                (float) $request->validated('amount_days'),
                $request->validated('reason'),
                (int) ($request->validated('year') ?? now()->format('Y')),
            );
        } catch (VacationRequestException $e) {
            throw ValidationException::withMessages([
                'amount_days' => __($e->getMessage()),
            ]);
        }

        return redirect()
            ->route('team-leader.vacation-credit')
            ->with('success', __('vacations.credit.flash.added'));
    }

    public function history(Request $request, User $employee): View
    {
        abort_unless($this->balances->actorMayManage($request->user(), $employee), 403);

        $year = (int) $request->integer('year', (int) now()->format('Y'));
        $balance = $this->balances->balanceFor($employee, $year);
        $balance->load(['creditLogs.addedBy']);

        return view('team-leader.vacation-credit.history', [
            'employee' => $employee,
            'year' => $year,
            'balance' => $balance,
            'logs' => $balance->creditLogs()->with('addedBy')->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }
}
