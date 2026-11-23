<?php

namespace App\Http\Requests\GeneralHouse\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

trait ValidatesGhProductOfferings
{
    /** @return array<string, mixed> */
    protected function offeringRules(): array
    {
        return [
            'offerings' => ['nullable', 'array'],
            'offerings.*.treatment_id' => ['required', 'integer', 'exists:gh_treatments,id', 'distinct'],
            'offerings.*.enabled' => ['nullable', 'boolean'],
            'offerings.*.standard_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'offerings.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    protected function validateEnabledOfferingPrices(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $rows = $this->input('offerings', []);
            if (! is_array($rows)) {
                return;
            }

            $categoryId = (int) $this->input('category_id', 0);
            $mapped = $categoryId > 0
                ? DB::table('gh_category_treatment')
                    ->where('category_id', $categoryId)
                    ->pluck('treatment_id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
                : [];
            $mappedSet = array_fill_keys($mapped, true);

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $treatmentId = (int) ($row['treatment_id'] ?? 0);
                $enabled = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || ($row['enabled'] ?? null) === 1
                    || ($row['enabled'] ?? null) === '1';

                if ($treatmentId > 0 && $categoryId > 0 && ! isset($mappedSet[$treatmentId])) {
                    $v->errors()->add(
                        "offerings.{$index}.treatment_id",
                        __('gh.errors.treatment_not_in_category')
                    );

                    continue;
                }

                if (! $enabled) {
                    continue;
                }

                $price = $row['standard_price'] ?? null;
                if ($price === null || $price === '') {
                    $v->errors()->add(
                        "offerings.{$index}.standard_price",
                        __('gh.errors.price_required')
                    );
                }
            }
        });
    }
}
