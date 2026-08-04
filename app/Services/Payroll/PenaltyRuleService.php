<?php

namespace App\Services\Payroll;

use App\Models\PenaltyRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed penalty_rules (T-4.2). Cached (file driver); invalidate on write.
 */
class PenaltyRuleService
{
    private const CACHE_KEY = 'penalty_rules.active';

    /** @return Collection<int, PenaltyRule> */
    public function listAll(): Collection
    {
        return PenaltyRule::query()
            ->orderBy('currency')
            ->orderBy('type')
            ->get();
    }

    public function findActive(string $type, string $currency): ?PenaltyRule
    {
        return $this->activeMap()->get($this->mapKey($type, $currency));
    }

    /**
     * @param  array{name: string, type: string, currency: string, amount_per_unit: float|string, unit: string, double_penalty_multiplier: float|string, is_active?: bool}  $data
     */
    public function create(array $data): PenaltyRule
    {
        $rule = PenaltyRule::query()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'currency' => $data['currency'],
            'amount_per_unit' => $data['amount_per_unit'],
            'unit' => $data['unit'],
            'double_penalty_multiplier' => $data['double_penalty_multiplier'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->forgetCache();

        return $rule;
    }

    /**
     * @param  array{name: string, amount_per_unit: float|string, unit: string, double_penalty_multiplier: float|string, is_active: bool}  $data
     */
    public function update(PenaltyRule $rule, array $data): PenaltyRule
    {
        $rule->update([
            'name' => $data['name'],
            'amount_per_unit' => $data['amount_per_unit'],
            'unit' => $data['unit'],
            'double_penalty_multiplier' => $data['double_penalty_multiplier'],
            'is_active' => $data['is_active'],
        ]);

        $this->forgetCache();

        return $rule->refresh();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return Collection<string, PenaltyRule> */
    private function activeMap(): Collection
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return PenaltyRule::query()
                ->where('is_active', true)
                ->get()
                ->keyBy(fn (PenaltyRule $rule) => $this->mapKey($rule->type, $rule->currency));
        });
    }

    private function mapKey(string $type, string $currency): string
    {
        return $type.'|'.$currency;
    }
}
