<?php

namespace App\Services\Attendance;

use App\Models\AttendancePhoto;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Driver self-service media history (field + punch photos).
 */
class DriverMediaHistoryService
{
    public const MAX_RANGE_DAYS = 92;

    public const KINDS = ['all', 'FIELD', 'CLOCK_IN', 'CLOCK_OUT'];

    public const LOCATION = ['all', 'with_gps', 'outside', 'near', 'no_gps'];

    /**
     * @param  array{
     *     from: string,
     *     to: string,
     *     kind: string,
     *     location: string,
     *     q?: ?string,
     * }  $filters
     */
    public function paginate(User $driver, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = min(50, max(1, $perPage));

        $query = AttendancePhoto::query()
            ->with(['attendance'])
            ->whereHas('attendance', fn ($q) => $q->where('user_id', $driver->id))
            ->whereDate('uploaded_at', '>=', $filters['from'])
            ->whereDate('uploaded_at', '<=', $filters['to'])
            ->when(
                ($filters['kind'] ?? 'all') !== 'all',
                fn ($q) => $q->where('kind', $filters['kind']),
            )
            ->when(($filters['location'] ?? 'all') === 'with_gps', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lng'))
            ->when(($filters['location'] ?? 'all') === 'no_gps', fn ($q) => $q->where(function ($inner) {
                $inner->whereNull('lat')->orWhereNull('lng');
            }))
            ->when(($filters['location'] ?? 'all') === 'outside', fn ($q) => $q->where('outside_geofence', true))
            ->when(($filters['location'] ?? 'all') === 'near', fn ($q) => $q
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->where('outside_geofence', false))
            ->when(
                filled($filters['q'] ?? null),
                function ($q) use ($filters) {
                    $term = '%'.trim((string) $filters['q']).'%';
                    $q->where(function ($inner) use ($term) {
                        $inner->where('description', 'like', $term)
                            ->orWhere('original_name', 'like', $term);
                    });
                },
            )
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id');

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }
}
