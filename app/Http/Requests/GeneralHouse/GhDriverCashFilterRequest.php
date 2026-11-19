<?php

namespace App\Http\Requests\GeneralHouse;

use App\Support\GeneralHouse\GhCatalogAccess;
use App\Support\GeneralHouse\GhReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters shared by the custody dashboard, settlements list and driver-cash report. */
class GhDriverCashFilterRequest extends FormRequest
{
    public const TABS = ['collections', 'outstanding', 'settlements', 'adjustments', 'ledger'];

    public const EXPORTS = ['outstanding', 'by_driver', 'by_date', 'by_manager'];

    public function authorize(): bool
    {
        return GhCatalogAccess::mayManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(self::TABS)],
            'q' => ['nullable', 'string', 'max:100'],
            'preset' => ['nullable', 'string', Rule::in(GhReportPeriod::PRESETS)],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'state' => ['nullable', 'string', Rule::in(['active', 'reversed'])],
            'export' => ['nullable', 'string', Rule::in(self::EXPORTS)],
        ];
    }

    public function tab(): string
    {
        return (string) ($this->validated('tab') ?? 'collections');
    }

    /** No period chosen → full history. */
    public function periodOrNull(): ?GhReportPeriod
    {
        return $this->filled('preset') ? $this->period() : null;
    }

    /** @return array{driver_id: ?int, manager_id: ?int, state: ?string} */
    public function settlementFilters(): array
    {
        return [
            'driver_id' => $this->validated('driver_id') !== null ? (int) $this->validated('driver_id') : null,
            'manager_id' => $this->validated('manager_id') !== null ? (int) $this->validated('manager_id') : null,
            'state' => $this->validated('state'),
        ];
    }

    public function period(): GhReportPeriod
    {
        return GhReportPeriod::fromInput([
            'preset' => $this->validated('preset') ?? GhReportPeriod::PRESET_LAST_30,
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
        ]);
    }
}
