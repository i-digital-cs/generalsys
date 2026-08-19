<?php

namespace App\Services\Valuation;

use App\Models\User;
use App\Models\ValuationSheet;
use App\Services\Admin\SettingService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Month-to-date totals from daily valuation sheets (Hamad-Gross style).
 */
class ValuationStatsService
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array{
     *     month_label: string,
     *     currency: string,
     *     sheets: int,
     *     gross: float,
     *     target: float,
     *     gap: float,
     *     b2b: float,
     *     b2c: float,
     *     b2b_count: float,
     *     b2c_count: float,
     *     kpi_avg: float,
     *     b2b_rate: float,
     *     b2c_rate: float
     * }
     */
    public function monthSnapshot(User $actor, string $module, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->timezone((string) config('app.timezone'));
        $row = $this->monthRow(
            $actor,
            $module,
            $now->copy()->startOfMonth()->toDateString(),
            $now->copy()->endOfMonth()->toDateString(),
        );
        $gross = round((float) ($row->gross ?? 0), 2);
        $target = round((float) ($row->target ?? 0), 2);

        return [
            'month_label' => $now->translatedFormat('F Y'),
            'currency' => $this->settings->get(SettingService::KEY_DEFAULT_CURRENCY, 'EGP') ?: 'EGP',
            'sheets' => (int) ($row->sheets ?? 0),
            'gross' => $gross,
            'target' => $target,
            'gap' => round($target - $gross, 2),
            'b2b' => round((float) ($row->b2b ?? 0), 2),
            'b2c' => round((float) ($row->b2c ?? 0), 2),
            'b2b_count' => round((float) ($row->b2b_count ?? 0), 2),
            'b2c_count' => round((float) ($row->b2c_count ?? 0), 2),
            'kpi_avg' => round((float) ($row->kpi_avg ?? 0), 6),
            'b2b_rate' => round((float) ($row->b2b_rate ?? 0), 6),
            'b2c_rate' => round((float) ($row->b2c_rate ?? 0), 6),
        ];
    }

    private function monthRow(User $actor, string $module, string $from, string $to): stdClass
    {
        $query = ValuationSheet::query()
            ->where('module', $module)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to);

        if (! $actor->isManager()) {
            $query->where('user_id', $actor->id);
        }

        // JSON paths: aggregations in SQL (AGENTS.md §4.3); sqlite tests use json_extract.
        return $query->toBase()->selectRaw(implode(', ', [
            'COUNT(*) as sheets',
            'COALESCE(SUM('.$this->jsonNum('result', '$.actual_amount_total').'), 0) as gross',
            'COALESCE(SUM('.$this->jsonNum('input', '$.target_amount_total').'), 0) as target',
            'COALESCE(SUM('.$this->jsonNum('input', '$.actual_b2b_amount').'), 0) as b2b',
            'COALESCE(SUM('.$this->jsonNum('input', '$.actual_b2c_amount').'), 0) as b2c',
            'COALESCE(SUM('.$this->jsonNum('input', '$.actual_b2b_count').'), 0) as b2b_count',
            'COALESCE(SUM('.$this->jsonNum('input', '$.actual_b2c_count').'), 0) as b2c_count',
            'COALESCE(AVG('.$this->jsonNum('result', '$.kpi_ratio').'), 0) as kpi_avg',
            'COALESCE(AVG('.$this->jsonNum('result', '$.amount_b2b_ratio').'), 0) as b2b_rate',
            'COALESCE(AVG('.$this->jsonNum('result', '$.amount_b2c_ratio').'), 0) as b2c_rate',
        ]))->first() ?? (object) [];
    }

    private function jsonNum(string $column, string $path): string
    {
        $pathSql = DB::getPdo()->quote($path);

        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'CAST(json_extract('.$column.', '.$pathSql.') AS REAL)';
        }

        return 'CAST(JSON_UNQUOTE(JSON_EXTRACT('.$column.', '.$pathSql.')) AS DECIMAL(12,2))';
    }
}
