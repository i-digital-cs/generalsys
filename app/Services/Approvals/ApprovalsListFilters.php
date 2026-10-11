<?php

namespace App\Services\Approvals;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Shared status / month / user / search filters for the Approvals inbox.
 */
final class ApprovalsListFilters
{
    /** @var list<string> */
    public const STATUSES = ['pending', 'approved', 'rejected', 'decided', 'all'];

    public function __construct(
        public readonly string $status,
        public readonly ?string $month,
        public readonly ?int $userId,
        public readonly string $q,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $status = $request->string('filter')->toString();
        if ($status === '') {
            $status = 'pending';
        }
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $month = $request->string('month')->toString();
        if ($month !== '' && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = '';
        }

        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        if ($userId !== null && $userId <= 0) {
            $userId = null;
        }

        $q = trim($request->string('q')->toString());
        if (mb_strlen($q) > 100) {
            $q = mb_substr($q, 0, 100);
        }

        return new self($status, $month !== '' ? $month : null, $userId, $q);
    }

    public static function fromStatus(string $status): self
    {
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        return new self($status, null, null, '');
    }

    /** @return array<string, string|int> */
    public function queryParams(): array
    {
        $params = ['filter' => $this->status];
        if ($this->month !== null) {
            $params['month'] = $this->month;
        }
        if ($this->userId !== null) {
            $params['user_id'] = $this->userId;
        }
        if ($this->q !== '') {
            $params['q'] = $this->q;
        }

        return $params;
    }

    public function monthStart(): ?Carbon
    {
        if ($this->month === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    public function monthEnd(): ?Carbon
    {
        return $this->monthStart()?->copy()->endOfMonth();
    }

    /**
     * Restrict by employee id and/or name/email search.
     *
     * @param  Builder<Model>  $query
     */
    public function applyEmployeeFilters(
        Builder $query,
        string $userIdColumn = 'user_id',
        string $userRelation = 'user',
    ): void {
        if ($this->userId !== null) {
            $query->where($userIdColumn, $this->userId);
        }

        if ($this->q === '') {
            return;
        }

        $like = '%'.$this->q.'%';
        $query->whereHas($userRelation, function (Builder $user) use ($like): void {
            $user->where(function (Builder $inner) use ($like): void {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDateMonth(Builder $query, string $column): void
    {
        $start = $this->monthStart();
        $end = $this->monthEnd();
        if ($start === null || $end === null) {
            return;
        }

        $query->whereBetween($column, [$start->toDateString(), $end->toDateString()]);
    }
}
