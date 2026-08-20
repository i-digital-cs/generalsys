<?php

namespace App\Services\Valuation;

use App\Exceptions\ValuationException;
use App\Models\User;
use App\Models\ValuationSheet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ValuationSheetService
{
    public function __construct(
        private readonly ValuationCalculator $calculator,
        private readonly ValuationAssignmentService $assignments,
    ) {}

    public function paginateFor(User $actor, string $module): LengthAwarePaginator
    {
        $query = ValuationSheet::query()
            ->with('user')
            ->where('module', $module)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (! $actor->isManager()) {
            $query->where('user_id', $actor->id);
        }

        return $query->paginate(25);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(User $actor, User $owner, string $module, string $date, array $input, string $status): ValuationSheet
    {
        $this->assertMayWrite($actor, $owner, $module);

        $merged = array_merge(ValuationFields::defaults(), $input);
        $merged['drivers'] = ValuationFields::normalizeDrivers($merged['drivers'] ?? []);
        $result = $this->calculator->compute($merged);
        if ($merged['drivers'] !== []) {
            $merged['drivers_score'] = $result['drivers_score_avg'];
        }

        return DB::transaction(function () use ($owner, $module, $date, $merged, $result, $status) {
            $sheet = ValuationSheet::query()->firstOrNew([
                'user_id' => $owner->id,
                'module' => $module,
                'date' => $date,
            ]);
            $sheet->fill([
                'status' => $status,
                'input' => $merged,
                'result' => $result,
            ]);
            $sheet->save();

            return $sheet->fresh(['user']);
        });
    }

    public function findForForm(User $actor, string $module, string $date): ValuationSheet
    {
        $sheet = ValuationSheet::query()
            ->where('user_id', $actor->id)
            ->where('module', $module)
            ->whereDate('date', $date)
            ->first();

        if ($sheet !== null) {
            return $sheet;
        }

        $draft = new ValuationSheet([
            'user_id' => $actor->id,
            'module' => $module,
            'date' => $date,
            'status' => ValuationSheet::STATUS_DRAFT,
            'input' => ValuationFields::defaults(),
            'result' => $this->calculator->compute(ValuationFields::defaults()),
        ]);
        $draft->setRelation('user', $actor);

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(array $input): array
    {
        return $this->calculator->compute(array_merge(ValuationFields::defaults(), $input));
    }

    private function assertMayWrite(User $actor, User $owner, string $module): void
    {
        if ($actor->is($owner) && $this->assignments->isAssigned($owner, $module)) {
            return;
        }

        throw ValuationException::notAllowed();
    }

    public function assertMayAccessModule(User $actor, string $module): void
    {
        if ($actor->isManager()) {
            return;
        }

        if ($this->assignments->isAssigned($actor, $module)) {
            return;
        }

        throw ValuationException::notAssigned();
    }
}
