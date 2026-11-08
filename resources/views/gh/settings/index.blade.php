@extends('layouts.app')

@section('title', __('gh.settings.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.settings.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.settings.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.offerings.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.offerings.title') }}</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h2 class="h6 card-title mb-0">{{ __('gh.settings.vat_section') }}</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-3 small">
                        <dt class="col-sm-6">{{ __('gh.settings.current_vat_rate') }}</dt>
                        <dd class="col-sm-6 hp-value num-ltr">{{ number_format((float) $standardVatRate, 2) }}%</dd>
                        <dt class="col-sm-6">{{ __('gh.settings.vat_source') }}</dt>
                        <dd class="col-sm-6">
                            @if ($vatFromDatabase)
                                {{ __('gh.settings.vat_source_database') }}
                            @else
                                {{ __('gh.settings.vat_source_env', ['rate' => number_format((float) $vatEnvFallback, 2)]) }}
                            @endif
                        </dd>
                        <dt class="col-sm-6">{{ __('gh.settings.default_offering_tax_class') }}</dt>
                        <dd class="col-sm-6">{{ __('gh.tax_class.'.$defaultOfferingTaxClass) }}</dd>
                        <dt class="col-sm-6">{{ __('gh.settings.pricing_basis') }}</dt>
                        <dd class="col-sm-6">{{ __('gh.settings.pricing_tax_exclusive') }}</dd>
                    </dl>

                    <form method="post" action="{{ route('gh.settings.vat.update') }}" class="border-top pt-3">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label" for="standard_vat_rate">{{ __('gh.settings.standard_vat_rate') }} (%)</label>
                            <input
                                type="number"
                                class="form-control @error('standard_vat_rate') is-invalid @enderror"
                                id="standard_vat_rate"
                                name="standard_vat_rate"
                                value="{{ old('standard_vat_rate', number_format((float) $standardVatRate, 2, '.', '')) }}"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                inputmode="decimal"
                            >
                            @error('standard_vat_rate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('gh.settings.vat_rate_hint') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="vat_reason">{{ __('gh.settings.vat_change_reason') }}</label>
                            <textarea
                                class="form-control @error('reason') is-invalid @enderror"
                                id="vat_reason"
                                name="reason"
                                rows="2"
                                maxlength="500"
                                required
                            >{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm(@json(__('gh.settings.vat_confirm')))">
                            {{ __('gh.settings.save_vat') }}
                        </button>
                    </form>

                    <p class="small text-body-secondary mt-3 mb-0">{{ __('gh.settings.vat_admin_note') }}</p>
                    <p class="small text-body-secondary mb-0">{{ __('gh.settings.tax_class_note') }}</p>

                    @if (count($vatAudits) > 0)
                        <h3 class="h6 mt-4 mb-2">{{ __('gh.settings.vat_audit_heading') }}</h3>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('gh.settings.vat_audit_when') }}</th>
                                        <th>{{ __('gh.settings.vat_audit_actor') }}</th>
                                        <th class="text-end">{{ __('gh.settings.vat_audit_old') }}</th>
                                        <th class="text-end">{{ __('gh.settings.vat_audit_new') }}</th>
                                        <th>{{ __('gh.settings.vat_audit_reason') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($vatAudits as $audit)
                                        <tr>
                                            <td class="small num-ltr">{{ $audit->recorded_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                            <td class="small">{{ $audit->actor?->name ?? '—' }}</td>
                                            <td class="small text-end hp-value num-ltr">{{ $audit->old_rate !== null ? number_format((float) $audit->old_rate, 2).'%' : '—' }}</td>
                                            <td class="small text-end hp-value num-ltr">{{ number_format((float) $audit->new_rate, 2) }}%</td>
                                            <td class="small">{{ $audit->reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h2 class="h6 card-title mb-0">{{ __('gh.settings.min_order_section') }}</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-6">{{ __('gh.settings.default_min_policy') }}</dt>
                        <dd class="col-sm-6">{{ __('gh.min_order_policy.'.$defaultMinOrderPolicy) }}</dd>
                        <dt class="col-sm-6">{{ __('gh.settings.surcharge_enabled') }}</dt>
                        <dd class="col-sm-6">{{ $surchargeEnabled ? __('gh.settings.yes') : __('gh.settings.no') }}</dd>
                        <dt class="col-sm-6">{{ __('gh.settings.surcharge_tax_class') }}</dt>
                        <dd class="col-sm-6">
                            @if ($surchargeTaxClass)
                                {{ __('gh.tax_class.'.$surchargeTaxClass) }}
                            @else
                                <span class="text-body-secondary">{{ __('gh.invoices.tax_unresolved') }}</span>
                            @endif
                        </dd>
                    </dl>
                    <p class="small text-body-secondary mt-3 mb-0">{{ __('gh.settings.min_order_note') }}</p>
                    <a href="{{ route('gh.zones.index') }}" class="btn btn-link btn-sm px-0">{{ __('gh.zones.title') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
