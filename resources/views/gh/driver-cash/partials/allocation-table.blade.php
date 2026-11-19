<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead><tr>
            <th class="text-center">{{ __('gh.driver_cash.select') }}</th>
            <th>{{ __('gh.driver_cash.collection_ref') }}</th>
            <th>{{ __('gh.driver_cash.order') }}</th>
            <th>{{ __('gh.driver_cash.invoice') }}</th>
            <th>{{ __('gh.driver_cash.collected_at') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.amount') }}</th>
            <th class="text-end">{{ __('gh.driver_cash.remaining') }}</th>
            <th style="min-width: 11rem">{{ __('gh.driver_cash.allocate_amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($collections as $c)
            @php
                $checked = (bool) old('allocations.'.$c->id.'.selected');
                $error = $errors->first('allocations.'.$c->id.'.amount');
            @endphp
            <tr data-gh-alloc-row data-remaining="{{ $c->remaining_amount }}">
                <td class="text-center">
                    <input type="hidden" name="allocations[{{ $c->id }}][selected]" value="0">
                    <input class="form-check-input" type="checkbox" id="alloc_{{ $c->id }}" name="allocations[{{ $c->id }}][selected]" value="1" data-gh-alloc-select @checked($checked)>
                    <label class="visually-hidden" for="alloc_{{ $c->id }}">{{ __('gh.driver_cash.select') }} {{ $c->reference }}</label>
                </td>
                <td class="num-ltr text-nowrap">{{ $c->reference }}</td>
                <td class="num-ltr text-nowrap"><a href="{{ route('gh.orders.show', $c->order_id) }}" target="_blank" rel="noopener">{{ $c->order?->reference }}</a></td>
                <td class="num-ltr text-nowrap">{{ $c->order?->invoice?->number ?? '—' }}</td>
                <td class="num-ltr small text-nowrap">{{ $c->collected_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</td>
                <td class="text-end"><x-money :amount="$c->amount" currency="AED" /></td>
                <td class="text-end fw-semibold"><x-money :amount="$c->remaining_amount" currency="AED" /></td>
                <td>
                    <div class="d-flex gap-1">
                        <input type="number" step="0.01" min="0.01" max="{{ $c->remaining_amount }}" inputmode="decimal"
                               name="allocations[{{ $c->id }}][amount]" value="{{ old('allocations.'.$c->id.'.amount') }}"
                               class="form-control form-control-sm @if ($error) is-invalid @endif" data-gh-alloc-amount
                               aria-label="{{ __('gh.driver_cash.allocate_amount') }} {{ $c->reference }}" @disabled(! $checked)>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-gh-fill>{{ __('gh.driver_cash.fill_remaining') }}</button>
                    </div>
                    @if ($error)<div class="invalid-feedback d-block">{{ $error }}</div>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
