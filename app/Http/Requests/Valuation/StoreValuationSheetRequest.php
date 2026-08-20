<?php

namespace App\Http\Requests\Valuation;

use App\Models\User;
use App\Models\ValuationSheet;
use App\Services\Valuation\ValuationFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreValuationSheetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $input = is_array($this->input('input')) ? $this->input('input') : [];
        $input['drivers'] = ValuationFields::normalizeDrivers($input['drivers'] ?? []);

        $this->merge([
            'input' => array_merge(ValuationFields::defaults(), $input),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->valuationAssignments()->exists() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scoreRules = [];
        foreach (ValuationFields::SCORE_KEYS as $key) {
            $scoreRules['input.'.$key] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        return array_merge([
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in([ValuationSheet::STATUS_DRAFT, ValuationSheet::STATUS_SUBMITTED])],
            'input.market_status' => ['nullable', 'string', 'max:5000'],
            'input.orders_movement' => ['nullable', 'numeric', 'min:0'],
            'input.growth_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'input.recession_index' => ['nullable', 'numeric', 'min:0'],
            'input.sales_spread' => ['nullable', 'numeric', 'min:0'],
            'input.marketing_spread' => ['nullable', 'numeric', 'min:0'],
            'input.haroon_news' => ['nullable', 'string', 'max:5000'],
            'input.laundry_king_positives' => ['nullable', 'string', 'max:5000'],
            'input.laundry_king_negatives' => ['nullable', 'string', 'max:5000'],
            'input.laundry_mart_positives' => ['nullable', 'string', 'max:5000'],
            'input.laundry_mart_negatives' => ['nullable', 'string', 'max:5000'],
            'input.customer_care_logistics_notes' => ['nullable', 'string', 'max:5000'],
            'input.sales_notes' => ['nullable', 'string', 'max:5000'],
            'input.drivers_notes' => ['nullable', 'string', 'max:5000'],
            'input.drivers' => ['nullable', 'array'],
            'input.drivers.*.user_id' => [
                'nullable',
                'integer',
                'required_without:input.drivers.*.name',
                Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE),
            ],
            'input.drivers.*.name' => [
                'nullable',
                'string',
                'max:150',
                'required_without:input.drivers.*.user_id',
            ],
            'input.drivers.*.orders' => ['required', 'numeric', 'min:0'],
            'input.drivers.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'input.drivers.*.notes' => ['nullable', 'string', 'max:5000'],
            'input.marketing_notes' => ['nullable', 'string', 'max:5000'],
            'input.graphic_notes' => ['nullable', 'string', 'max:5000'],
            'input.social_requirements_notes' => ['nullable', 'string', 'max:5000'],
            'input.hr_followup_notes' => ['nullable', 'string', 'max:5000'],
            'input.software_notes' => ['nullable', 'string', 'max:5000'],
            'input.accounting_notes' => ['nullable', 'string', 'max:5000'],
            'input.top_management_notes' => ['nullable', 'string', 'max:5000'],
            'input.quality_notes' => ['nullable', 'string', 'max:5000'],
            'input.customer_care_notes' => ['nullable', 'string', 'max:5000'],
            'input.target_b2b_count' => ['nullable', 'numeric', 'min:0'],
            'input.target_b2c_count' => ['nullable', 'numeric', 'min:0'],
            'input.actual_b2b_count' => ['nullable', 'numeric', 'min:0'],
            'input.actual_b2c_count' => ['nullable', 'numeric', 'min:0'],
            'input.target_count_total' => ['nullable', 'numeric', 'min:0'],
            'input.target_b2b_amount' => ['nullable', 'numeric', 'min:0'],
            'input.target_b2c_amount' => ['nullable', 'numeric', 'min:0'],
            'input.actual_b2b_amount' => ['nullable', 'numeric', 'min:0'],
            'input.actual_b2c_amount' => ['nullable', 'numeric', 'min:0'],
            'input.target_amount_total' => ['nullable', 'numeric', 'min:0'],
            'input.kpi_base' => ['nullable', 'numeric', 'min:0'],
            'input.cashflow_base' => ['nullable', 'numeric', 'min:0'],
        ], $scoreRules);
    }
}
