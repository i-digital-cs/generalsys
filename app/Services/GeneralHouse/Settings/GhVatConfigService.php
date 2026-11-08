<?php

namespace App\Services\GeneralHouse\Settings;

use App\Models\GeneralHouse\GhVatRateAudit;
use App\Models\User;
use App\Services\Admin\SettingService;
use App\Support\GeneralHouse\GhMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative GH standard VAT rate: DB setting → env/config fallback.
 * New invoice drafts stamp this rate; existing drafts/issued rows keep theirs.
 */
class GhVatConfigService
{
    public const SETTING_KEY = SettingService::KEY_GH_STANDARD_VAT_RATE;

    public function __construct(
        private readonly SettingService $settings,
    ) {}

    public function standardRate(): string
    {
        $stored = $this->settings->get(self::SETTING_KEY);
        if ($stored !== null && $stored !== '') {
            return $this->normalizeRate($stored);
        }

        return $this->normalizeRate((string) config('general_house.standard_vat_rate', '5.00'));
    }

    public function hasDatabaseOverride(): bool
    {
        $stored = $this->settings->get(self::SETTING_KEY);

        return $stored !== null && $stored !== '';
    }

    /**
     * @return list<GhVatRateAudit>
     */
    public function recentAudits(int $limit = 10): array
    {
        return GhVatRateAudit::query()
            ->with('actor')
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function updateStandardRate(User $actor, string|float|int $rate, string $reason): string
    {
        if (! $actor->isManager()) {
            throw ValidationException::withMessages([
                'standard_vat_rate' => [__('gh.errors.vat_rate_forbidden')],
            ]);
        }

        $normalized = $this->normalizeRate($rate);
        $this->assertValidRate($normalized);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => [__('gh.errors.vat_rate_reason_required')],
            ]);
        }

        $old = $this->standardRate();
        if (GhMoney::cmp($old, $normalized) === 0) {
            return $normalized;
        }

        return DB::transaction(function () use ($actor, $normalized, $reason, $old) {
            $this->settings->set(self::SETTING_KEY, $normalized);

            GhVatRateAudit::query()->create([
                'actor_id' => $actor->id,
                'old_rate' => $old,
                'new_rate' => $normalized,
                'reason' => $reason,
                'recorded_at' => Carbon::now('UTC'),
            ]);

            return $normalized;
        });
    }

    private function normalizeRate(string|float|int $rate): string
    {
        return GhMoney::of($rate);
    }

    private function assertValidRate(string $rate): void
    {
        if (GhMoney::cmp($rate, '0') < 0 || GhMoney::cmp($rate, '100') > 0) {
            throw ValidationException::withMessages([
                'standard_vat_rate' => [__('gh.errors.vat_rate_invalid')],
            ]);
        }
    }
}
