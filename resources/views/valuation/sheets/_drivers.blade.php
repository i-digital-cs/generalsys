@php
    use App\Services\Valuation\ValuationCopy;
    use App\Services\Valuation\ValuationFields;

    $module = $module ?? 'service';
    $line = fn (string $key, array $replace = []) => ValuationCopy::line($module, $key, $replace);
    $editable = $editable ?? true;
    $employees = $employees ?? collect();
    $driverUsers = $driverUsers ?? collect();
    $result = $result ?? [];
    $target = ValuationFields::DRIVER_ORDERS_TARGET;
    $driverRows = old('input.drivers', $input['drivers'] ?? []);
    if (! is_array($driverRows) || $driverRows === []) {
        $driverRows = [['user_id' => '', 'name' => '', 'orders' => '', 'score' => '', 'notes' => '']];
    }
    $employeeLabel = function ($user) {
        if ($user === null) {
            return '—';
        }
        $name = app()->getLocale() === 'ar'
            ? ($user->name_ar ?: $user->name_en)
            : ($user->name_en ?: $user->name_ar);

        return $name ?: '—';
    };
    $resolveDriverName = function (array $row) use ($driverUsers, $employeeLabel): string {
        return ValuationFields::driverDisplayName($row, function (int $id) use ($driverUsers, $employeeLabel) {
            return $employeeLabel($driverUsers->get($id));
        });
    };
    $avgScore = $result['drivers_score_avg'] ?? null;
@endphp
<div class="col-12">
    <div class="card border-0 shadow-sm h-100" data-drivers-block
         data-orders-target="{{ $target }}"
         data-over-template="{{ __('valuation.drivers.over_target', ['x' => ':x']) }}">
        <div class="card-header bg-transparent fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>{{ $line('sections.drivers') }}</span>
            @if (! $editable && $avgScore !== null && ($result['drivers'] ?? []) !== [])
                <span class="small fw-normal d-inline-flex align-items-center gap-2">
                    <span class="text-body-secondary">{{ __('valuation.drivers.avg_score') }}:</span>
                    <span class="num-ltr fw-semibold">{{ number_format((float) $avgScore, 2) }}%</span>
                    <x-stars :score="$avgScore" />
                </span>
            @endif
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                <h3 class="h6 mb-0">{{ __('valuation.drivers.list') }}</h3>
                <span class="small text-body-secondary">{{ __('valuation.drivers.orders_target', ['n' => $target]) }}</span>
            </div>
            <p class="small text-body-secondary mb-3">{{ __('valuation.drivers.or_manual_hint') }}</p>

            @if ($editable)
                <div id="driver-rows" data-prefix="input[drivers]">
                    @foreach ($driverRows as $i => $row)
                        @php
                            $orders = $row['orders'] ?? '';
                            $over = is_numeric($orders) ? ValuationFields::driverOverTarget((float) $orders) : 0;
                            $hasUser = (string) ($row['user_id'] ?? '') !== '';
                            $rowScore = $row['score'] ?? '';
                        @endphp
                        <div class="border rounded-3 p-3 mb-3" data-row data-driver-row>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="driver_user_{{ $i }}">{{ __('valuation.drivers.employee') }}</label>
                                    <select class="form-select @error('input.drivers.'.$i.'.user_id') is-invalid @enderror"
                                            data-field="user_id" data-driver-user
                                            name="input[drivers][{{ $i }}][user_id]" id="driver_user_{{ $i }}">
                                        <option value="">{{ __('valuation.drivers.select_employee') }}</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected((string) ($row['user_id'] ?? '') === (string) $employee->id)>
                                                {{ $employeeLabel($employee) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('input.drivers.'.$i.'.user_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="driver_name_{{ $i }}">{{ __('valuation.drivers.manual_name') }}</label>
                                    <input type="text" class="form-control @error('input.drivers.'.$i.'.name') is-invalid @enderror"
                                           data-field="name" data-driver-name
                                           name="input[drivers][{{ $i }}][name]" id="driver_name_{{ $i }}"
                                           value="{{ $row['name'] ?? '' }}" maxlength="150" autocomplete="off"
                                           @disabled($hasUser)
                                           placeholder="{{ __('valuation.drivers.manual_placeholder') }}">
                                    @error('input.drivers.'.$i.'.name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="driver_orders_{{ $i }}">{{ __('valuation.drivers.orders') }}</label>
                                    <input class="form-control @error('input.drivers.'.$i.'.orders') is-invalid @enderror"
                                           dir="ltr" data-field="orders" data-driver-orders
                                           name="input[drivers][{{ $i }}][orders]" id="driver_orders_{{ $i }}"
                                           value="{{ $orders }}" min="0" step="1" inputmode="decimal" autocomplete="off">
                                    @error('input.drivers.'.$i.'.orders')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 col-md-2">
                                    <div class="form-label">{{ __('valuation.drivers.over_target_label') }}</div>
                                    <p class="mb-0 small fw-semibold text-warning num-ltr @if ($over <= 0) d-none @endif" data-driver-over>
                                        {{ __('valuation.drivers.over_target', ['x' => $over]) }}
                                    </p>
                                    <p class="mb-0 small text-body-secondary @if ($over > 0) d-none @endif" data-driver-ok>—</p>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="driver_score_{{ $i }}">{{ __('valuation.fields.score') }}</label>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <input class="form-control @error('input.drivers.'.$i.'.score') is-invalid @enderror"
                                               dir="ltr" data-field="score" data-star-source
                                               name="input[drivers][{{ $i }}][score]" id="driver_score_{{ $i }}"
                                               value="{{ $rowScore }}" min="0" max="100" step="1" inputmode="decimal" autocomplete="off"
                                               style="min-width: 5.5rem; max-width: 8rem;">
                                        <x-stars :score="$rowScore === '' ? 0 : $rowScore" data-star-out class="flex-shrink-0" />
                                    </div>
                                    @error('input.drivers.'.$i.'.score')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-7">
                                    <label class="form-label" for="driver_notes_{{ $i }}">{{ __('valuation.fields.notes') }}</label>
                                    <textarea class="form-control @error('input.drivers.'.$i.'.notes') is-invalid @enderror"
                                              data-field="notes" rows="2"
                                              name="input[drivers][{{ $i }}][notes]" id="driver_notes_{{ $i }}">{{ $row['notes'] ?? '' }}</textarea>
                                    @error('input.drivers.'.$i.'.notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row
                                            aria-label="{{ __('valuation.drivers.remove') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <template id="driver-row-template">
                    <div class="border rounded-3 p-3 mb-3" data-row data-driver-row>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('valuation.drivers.employee') }}</label>
                                <select class="form-select" data-field="user_id" data-driver-user
                                        name="input[drivers][__INDEX__][user_id]">
                                    <option value="">{{ __('valuation.drivers.select_employee') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employeeLabel($employee) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('valuation.drivers.manual_name') }}</label>
                                <input type="text" class="form-control" data-field="name" data-driver-name
                                       name="input[drivers][__INDEX__][name]" maxlength="150" autocomplete="off"
                                       placeholder="{{ __('valuation.drivers.manual_placeholder') }}">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">{{ __('valuation.drivers.orders') }}</label>
                                <input class="form-control" dir="ltr" data-field="orders" data-driver-orders
                                       name="input[drivers][__INDEX__][orders]" min="0" step="1" inputmode="decimal" autocomplete="off">
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="form-label">{{ __('valuation.drivers.over_target_label') }}</div>
                                <p class="mb-0 small fw-semibold text-warning num-ltr d-none" data-driver-over></p>
                                <p class="mb-0 small text-body-secondary" data-driver-ok>—</p>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">{{ __('valuation.fields.score') }}</label>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <input class="form-control" dir="ltr" data-field="score" data-star-source
                                           name="input[drivers][__INDEX__][score]" min="0" max="100" step="1" inputmode="decimal" autocomplete="off">
                                    <span data-star-out class="stars text-warning" dir="ltr" aria-hidden="true">
                                        <i class="bi bi-star"></i><i class="bi bi-star"></i><i class="bi bi-star"></i><i class="bi bi-star"></i><i class="bi bi-star"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label">{{ __('valuation.fields.notes') }}</label>
                                <textarea class="form-control" data-field="notes" rows="2"
                                          name="input[drivers][__INDEX__][notes]"></textarea>
                            </div>
                            <div class="col-12 col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row
                                        aria-label="{{ __('valuation.drivers.remove') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" class="btn btn-outline-primary btn-sm mt-1"
                        data-add-row="driver-row-template" data-target="driver-rows"
                        data-prefix="input[drivers]">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('valuation.drivers.add') }}
                </button>
            @else
                @php
                    $savedDrivers = is_array($result['drivers'] ?? null) ? $result['drivers'] : ValuationFields::normalizeDrivers($input['drivers'] ?? []);
                @endphp
                @if ($savedDrivers === [])
                    <p class="mb-0 text-body-secondary">{{ __('valuation.drivers.empty') }}</p>
                @else
                    <div class="d-flex flex-column gap-3">
                        @foreach ($savedDrivers as $row)
                            @php
                                $orders = (float) ($row['orders'] ?? 0);
                                $over = (float) ($row['over_target'] ?? ValuationFields::driverOverTarget($orders));
                                $rowScore = (float) ($row['score'] ?? 0);
                                $rowNotes = trim((string) ($row['notes'] ?? ''));
                            @endphp
                            <div class="border rounded-3 p-3">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                    <div class="fw-semibold">{{ $resolveDriverName($row) }}</div>
                                    <div class="small">
                                        <span class="text-body-secondary">{{ __('valuation.drivers.orders') }}:</span>
                                        <span class="num-ltr">{{ $orders }}</span>
                                        @if ($over > 0)
                                            · <span class="text-warning fw-semibold num-ltr">{{ __('valuation.drivers.over_target', ['x' => $over]) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="text-body-secondary small">{{ __('valuation.fields.score') }}:</span>
                                    <span class="num-ltr fw-semibold">{{ number_format($rowScore, 2) }}%</span>
                                    <x-stars :score="$rowScore" />
                                </div>
                                <div>
                                    <div class="text-body-secondary small mb-1">{{ __('valuation.fields.notes') }}</div>
                                    <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $rowNotes === '' ? '—' : $rowNotes }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
