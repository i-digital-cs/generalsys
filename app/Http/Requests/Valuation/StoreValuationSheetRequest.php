<?php

namespace App\Http\Requests\Valuation;

use App\Models\User;
use App\Models\ValuationSheet;
use App\Services\Valuation\ValuationCopy;
use App\Services\Valuation\ValuationFields;
use App\Support\AppModule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreValuationSheetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $module = (string) $this->route('module');
        $input = is_array($this->input('input')) ? $this->input('input') : [];
        $input['drivers'] = ValuationFields::normalizeDrivers($input['drivers'] ?? []);

        $this->merge([
            'input' => array_merge(ValuationCopy::defaults($module), $this->sanitizeInputNumbers($input)),
        ]);
    }

    /**
     * Strip % / commas so typed scores like "80%" still validate as numbers.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function sanitizeInputNumbers(array $input): array
    {
        $numericKeys = array_merge(ValuationFields::SCORE_KEYS, [
            'orders_movement',
            'growth_percent',
            'recession_index',
            'sales_spread',
            'marketing_spread',
            'target_b2b_count',
            'target_b2c_count',
            'actual_b2b_count',
            'actual_b2c_count',
            'target_count_total',
            'target_b2b_amount',
            'target_b2c_amount',
            'actual_b2b_amount',
            'actual_b2c_amount',
            'target_amount_total',
            'kpi_base',
            'cashflow_base',
        ]);

        foreach ($numericKeys as $key) {
            if (! array_key_exists($key, $input) || ! is_string($input[$key])) {
                continue;
            }
            $cleaned = str_replace(['%', ',', ' '], ['', '.', ''], trim($input[$key]));
            $input[$key] = $cleaned === '' ? null : $cleaned;
        }

        if (isset($input['drivers']) && is_array($input['drivers'])) {
            foreach ($input['drivers'] as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['orders', 'score'] as $field) {
                    if (! isset($row[$field]) || ! is_string($row[$field])) {
                        continue;
                    }
                    $cleaned = str_replace(['%', ',', ' '], ['', '.', ''], trim($row[$field]));
                    $input['drivers'][$i][$field] = $cleaned === '' ? null : $cleaned;
                }
            }
        }

        return $input;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $module = (string) $this->route('module');

        if ($user === null || ! AppModule::mayAccessModule($user, $module)) {
            return false;
        }

        return $user->valuationAssignments()->where('module', $module)->exists();
    }

    /**
     * Always return to the fill form (never rely on Referer / previous URL).
     */
    protected function failedValidation(Validator $validator): void
    {
        $module = (string) $this->route('module');
        $date = (string) ($this->input('date') ?: now()->toDateString());

        throw (new ValidationException($validator))
            ->errorBag('default')
            ->redirectTo(route('valuation.sheets.form', [
                'module' => $module,
                'date' => $date,
            ]));
    }

    protected function failedAuthorization(): void
    {
        $module = (string) $this->route('module');
        $date = (string) ($this->input('date') ?: now()->toDateString());

        throw ValidationException::withMessages([
            'date' => __('valuation.errors.not_assigned'),
        ])->redirectTo(route('valuation.sheets.form', [
            'module' => $module,
            'date' => $date,
        ]));
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
            // Growth / recession indexes are not capped at 100 (Mohra “growth index”).
            'input.growth_percent' => ['nullable', 'numeric', 'min:0'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attrs = [
            'date' => __('valuation.date'),
            'status' => __('valuation.status'),
            'input.drivers' => __('valuation.drivers.list'),
            'input.orders_movement' => __('valuation.fields.orders_movement'),
            'input.growth_percent' => __('valuation.fields.growth_percent'),
        ];

        foreach (ValuationFields::SCORE_KEYS as $key) {
            $attrs['input.'.$key] = __('valuation.fields.score');
        }

        $drivers = is_array($this->input('input.drivers')) ? $this->input('input.drivers') : [];
        foreach (array_keys($drivers) as $i) {
            $n = ((int) $i) + 1;
            $attrs['input.drivers.'.$i.'.user_id'] = __('valuation.drivers.employee').' #'.$n;
            $attrs['input.drivers.'.$i.'.name'] = __('valuation.drivers.manual_name').' #'.$n;
            $attrs['input.drivers.'.$i.'.orders'] = __('valuation.drivers.orders').' #'.$n;
            $attrs['input.drivers.'.$i.'.score'] = __('valuation.fields.score').' #'.$n;
            $attrs['input.drivers.'.$i.'.notes'] = __('valuation.fields.notes').' #'.$n;
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        $drivers = is_array($this->input('input.drivers')) ? $this->input('input.drivers') : [];
        foreach (array_keys($drivers) as $i) {
            $n = ((int) $i) + 1;
            $messages['input.drivers.'.$i.'.user_id.required_without'] = __('valuation.errors.driver_identity', ['n' => $n]);
            $messages['input.drivers.'.$i.'.name.required_without'] = __('valuation.errors.driver_identity', ['n' => $n]);
            $messages['input.drivers.'.$i.'.orders.required'] = __('valuation.errors.driver_orders', ['n' => $n]);
            $messages['input.drivers.'.$i.'.score.max'] = __('valuation.errors.driver_score', ['n' => $n]);
            $messages['input.drivers.'.$i.'.score.min'] = __('valuation.errors.driver_score', ['n' => $n]);
        }

        return $messages;
    }
}
