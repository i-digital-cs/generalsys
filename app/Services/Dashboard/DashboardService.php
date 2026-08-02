<?php

namespace App\Services\Dashboard;

use App\Models\OfficialHoliday;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Data for the generic authenticated dashboard (TEAM_LEADER home until the
 * Phase 2 team dashboard ships — see routes/web.php).
 */
class DashboardService
{
    /**
     * Menu items of the given role, resolved to URLs and filtered to routes
     * that are actually registered (same rule as the sidebar).
     *
     * @return array<int, array{url: string, icon: string, key: string}>
     */
    public function quickActionsFor(string $role): array
    {
        return collect(config("menu.{$role}", config('menu.EMPLOYEE')))
            ->filter(fn (array $item) => Route::has($item['route']))
            ->map(fn (array $item) => [
                'url' => route($item['route']),
                'icon' => $item['icon'],
                'key' => $item['key'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, OfficialHoliday>
     */
    public function upcomingHolidays(int $limit = 5): Collection
    {
        return OfficialHoliday::where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit($limit)
            ->get();
    }
}
