@php
    /** @var \App\Support\GeneralHouse\GhReportFilters $filters */
    $preset = $filters->period->preset;
    $action = $action ?? url()->current();
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="{{ $action }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label" for="preset">{{ __('gh.reports.preset') }}</label>
                <select id="preset" name="preset" class="form-select form-select-sm">
                    @foreach ([
                        'today' => __('gh.reports.preset_today'),
                        'yesterday' => __('gh.reports.preset_yesterday'),
                        'last_7' => __('gh.reports.preset_last_7'),
                        'last_30' => __('gh.reports.preset_last_30'),
                        'custom' => __('gh.reports.preset_custom'),
                    ] as $val => $label)
                        <option value="{{ $val }}" @selected($preset === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="from">{{ __('gh.reports.from') }}</label>
                <input id="from" type="date" name="from" class="form-control form-control-sm" value="{{ $filters->period->fromDate }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="to">{{ __('gh.reports.to') }}</label>
                <input id="to" type="date" name="to" class="form-control form-control-sm" value="{{ $filters->period->toDate }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="customer_type">{{ __('gh.reports.customer_type') }}</label>
                <select id="customer_type" name="customer_type" class="form-select form-select-sm">
                    <option value="">{{ __('gh.reports.all') }}</option>
                    @foreach (\App\Support\GeneralHouse\GhCustomerType::ALL as $t)
                        <option value="{{ $t }}" @selected($filters->customerType === $t)>{{ __('gh.customer_type.'.$t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="customer_id">{{ __('gh.reports.customer') }}</label>
                <select id="customer_id" name="customer_id" class="form-select form-select-sm">
                    <option value="">{{ __('gh.reports.all') }}</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected($filters->customerId === (int) $c->id)>
                            {{ app()->getLocale()==='ar' ? $c->name_ar : ($c->name_en ?: $c->name_ar) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="zone_id">{{ __('gh.reports.zone') }}</label>
                <select id="zone_id" name="zone_id" class="form-select form-select-sm">
                    <option value="">{{ __('gh.reports.all') }}</option>
                    @foreach ($zones as $z)
                        <option value="{{ $z->id }}" @selected($filters->zoneId === (int) $z->id)>{{ $z->localizedName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="driver_id">{{ __('gh.reports.driver') }}</label>
                <select id="driver_id" name="driver_id" class="form-select form-select-sm">
                    <option value="">{{ __('gh.reports.all') }}</option>
                    @foreach ($driverOptions as $d)
                        <option value="{{ $d->id }}" @selected($filters->driverId === (int) $d->id)>
                            {{ app()->getLocale()==='ar' ? $d->name_ar : ($d->name_en ?: $d->name_ar) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="order_status">{{ __('gh.reports.order_status') }}</label>
                <select id="order_status" name="order_status" class="form-select form-select-sm">
                    <option value="">{{ __('gh.reports.all') }}</option>
                    @foreach (\App\Support\GeneralHouse\GhOrderStatus::ALL as $st)
                        <option value="{{ $st }}" @selected($filters->orderStatus === $st)>{{ __('gh.order_status.'.$st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="payment_status">{{ __('gh.reports.payment_status') }}</label>
                <select id="payment_status" name="payment_status" class="form-select form-select-sm">
                    <option value="">{{ __('gh.reports.all') }}</option>
                    @foreach (\App\Support\GeneralHouse\GhInvoicePaymentStatus::ALL as $ps)
                        <option value="{{ $ps }}" @selected($filters->paymentStatus === $ps)>{{ __('gh.payment_status.'.$ps) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.reports.apply') }}</button>
                @if (!empty($exportRoute))
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route($exportRoute, array_merge($query ?? [], ['export' => 1])) }}">{{ __('gh.reports.export_csv') }}</a>
                @endif
            </div>
        </form>
        <p class="small text-body-secondary mb-0 mt-2 num-ltr">
            {{ $filters->period->fromDate }} → {{ $filters->period->toDate }} ({{ $filters->period->timezone }})
        </p>
    </div>
</div>
