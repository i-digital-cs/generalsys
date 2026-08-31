@php
    use App\Services\Valuation\ValuationCopy;

    $module = $module ?? 'service';
    $editable = $editable ?? true;
    $line = fn (string $key, array $replace = []) => ValuationCopy::line($module, $key, $replace);
    $score = fn (string $key) => $editable
        ? old('input.'.$key, $input[$key] ?? 0)
        : ($input[$key] ?? 0);
    $text = fn (string $key) => $editable
        ? old('input.'.$key, $input[$key] ?? '')
        : (string) ($input[$key] ?? '');
    $blank = fn (string $value) => $value === '' ? '—' : $value;
@endphp

<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ $line('sections.market') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-label">{{ $line('fields.market_status') }}</div>
                    @if ($editable)
                        <textarea name="input[market_status]" id="market_status" class="form-control" rows="2">{{ $text('market_status') }}</textarea>
                    @else
                        <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text('market_status')) }}</p>
                    @endif
                </div>
                @foreach ([
                    'orders_movement',
                    'growth_percent',
                    'recession_index',
                    'sales_spread',
                    'marketing_spread',
                ] as $mk)
                    <div class="col-6 col-md-4 col-xl">
                        <div class="form-label">{{ $line('fields.'.$mk) }}</div>
                        @if ($editable)
                            <label class="visually-hidden" for="{{ $mk }}">{{ $line('fields.'.$mk) }}</label>
                            <input class="form-control" dir="ltr" name="input[{{ $mk }}]" id="{{ $mk }}" value="{{ $score($mk) }}">
                        @else
                            <p class="mb-0 num-ltr">{{ $score($mk) }}</p>
                        @endif
                    </div>
                @endforeach
                @if (ValuationCopy::showHaroonNewsInMarket($module))
                    <div class="col-12">
                        <div class="form-label">{{ $line('fields.haroon_news') }}</div>
                        @if ($editable)
                            <textarea name="input[haroon_news]" id="haroon_news" class="form-control" rows="2">{{ $text('haroon_news') }}</textarea>
                        @else
                            <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text('haroon_news')) }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if (ValuationCopy::showOperationFlowHeader($module))
    <div class="col-12">
        <p class="small fw-semibold text-body-secondary mb-0">{{ $line('sections.operation_flow') }}</p>
    </div>
@endif

@include('valuation.sheets._score-block', [
    'module' => $module,
    'key' => 'laundry_king',
    'scoreKey' => 'laundry_king_score',
    'extra' => ['laundry_king_positives', 'laundry_king_negatives'],
    'col' => 'col-12 col-lg-6',
    'editable' => $editable,
    'input' => $input,
])
@include('valuation.sheets._score-block', [
    'module' => $module,
    'key' => 'laundry_mart',
    'scoreKey' => 'laundry_mart_score',
    'extra' => ['laundry_mart_positives', 'laundry_mart_negatives'],
    'col' => 'col-12 col-lg-6',
    'editable' => $editable,
    'input' => $input,
])

@if (ValuationCopy::showOperationalNotesAfterSuppliers($module))
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="form-label">{{ $line('fields.haroon_news') }}</div>
                @if ($editable)
                    <textarea name="input[haroon_news]" id="haroon_news" class="form-control" rows="3">{{ $text('haroon_news') }}</textarea>
                @else
                    <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text('haroon_news')) }}</p>
                @endif
            </div>
        </div>
    </div>
@endif

@if (ValuationCopy::showLogisticsBlock($module))
    @include('valuation.sheets._score-block', [
        'module' => $module,
        'key' => 'customer_care_logistics',
        'scoreKey' => 'customer_care_logistics_score',
        'notesKey' => 'customer_care_logistics_notes',
        'editable' => $editable,
        'input' => $input,
    ])
@endif

<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ $line('sections.sales') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <p class="small text-body-secondary mb-0">{{ $line('sales_avg_hint') }}</p>
                </div>
                @foreach (ValuationCopy::salesChannelKeys() as $sk)
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="form-label">{{ $line('fields.'.$sk) }}</div>
                        @if ($editable)
                            <label class="visually-hidden" for="{{ $sk }}">{{ $line('fields.'.$sk) }}</label>
                            <input class="form-control" dir="ltr" name="input[{{ $sk }}]" id="{{ $sk }}"
                                   value="{{ $score($sk) }}" min="0" max="100" step="1" data-star-source data-sales-channel>
                            <x-stars :score="$score($sk)" class="small" data-star-out />
                        @else
                            <p class="mb-0"><span class="num-ltr">{{ $score($sk) }}%</span> <x-stars :score="$score($sk)" class="small" /></p>
                        @endif
                    </div>
                @endforeach
                <div class="col-12">
                    <p class="mb-1">
                        {{ $line('fields.sales_average') }}:
                        <span class="num-ltr fw-semibold" data-sales-avg>{{ number_format((float) ($result['sales_average'] ?? 0), 2) }}%</span>
                        <x-stars :score="$result['sales_average'] ?? 0" data-sales-stars />
                    </p>
                    <div class="form-label">{{ $line('fields.notes') }}</div>
                    @if ($editable)
                        <textarea name="input[sales_notes]" id="sales_notes" class="form-control" rows="2">{{ $text('sales_notes') }}</textarea>
                    @else
                        <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text('sales_notes')) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if (ValuationCopy::showDynamicDrivers($module))
    @include('valuation.sheets._drivers', [
        'module' => $module,
        'editable' => $editable,
        'input' => $input,
        'result' => $result ?? [],
        'employees' => $employees ?? collect(),
        'driverUsers' => $driverUsers ?? collect(),
    ])
@else
    @include('valuation.sheets._score-block', [
        'module' => $module,
        'key' => 'drivers',
        'scoreKey' => 'drivers_score',
        'notesKey' => 'drivers_notes',
        'editable' => $editable,
        'input' => $input,
    ])
@endif

@foreach (ValuationCopy::departmentBlocks($module) as [$key, $scoreKey, $notesKey])
    @include('valuation.sheets._score-block', [
        'module' => $module,
        'key' => $key,
        'scoreKey' => $scoreKey,
        'notesKey' => $notesKey,
        'editable' => $editable,
        'input' => $input,
    ])
@endforeach

<div class="col-12">
    @include('valuation.sheets._excel-targets', [
        'module' => $module,
        'editable' => $editable,
        'input' => $input,
        'result' => $result,
    ])
</div>

@if (ValuationCopy::showProviders($module))
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">{{ $line('sections.providers') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach (['provider_mart','provider_kings','provider_golden','provider_rug','work_photo','work_video','work_software','ads_google','ads_meta'] as $pk)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-label">{{ $line('fields.'.$pk) }}</div>
                            @if ($editable)
                                <label class="visually-hidden" for="{{ $pk }}">{{ $line('fields.'.$pk) }}</label>
                                <input class="form-control" dir="ltr" name="input[{{ $pk }}]" id="{{ $pk }}"
                                       value="{{ $score($pk) }}" min="0" max="100" step="1">
                            @else
                                <p class="mb-0 num-ltr">{{ $score($pk) }}%</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
