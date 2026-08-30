@php
    $index = $index ?? 0;
    $line = $line ?? [];
    $items = $items ?? collect();
    $mode = $line['discount_mode'] ?? 'amount';
    $selected = $items->firstWhere('id', (int) ($line['item_id'] ?? 0));
@endphp
<tr data-gross-line-row>
    <td>
        <select name="lines[{{ $index }}][item_id]" class="form-select form-select-sm" required data-line-item>
            <option value="">—</option>
            @foreach ($items as $item)
                @php
                    $bits = [$item->code, $item->name];
                    if ($item->color) {
                        $bits[] = $item->color;
                    }
                    if ($item->size) {
                        $bits[] = $item->size;
                    }
                    if ($item->model) {
                        $bits[] = $item->model;
                    }
                    $onHand = $item->balance?->qty_on_hand_pieces;
                    if ($onHand !== null) {
                        $bits[] = '('.$onHand.')';
                    }
                    $label = implode(' — ', $bits);
                @endphp
                <option value="{{ $item->id }}"
                        data-price="{{ $item->default_sale_price ?? '' }}"
                        data-size="{{ $item->size ?? '' }}"
                        data-search="{{ mb_strtolower($label) }}"
                        @selected(($line['item_id'] ?? '') == $item->id)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <input type="hidden" name="lines[{{ $index }}][unit]" value="PIECE">
    </td>
    <td class="small text-body-secondary" data-line-size>
        {{ $selected?->size ?: '—' }}
    </td>
    <td>
        <input type="number" min="1" name="lines[{{ $index }}][qty]" class="form-control form-control-sm"
               value="{{ $line['qty'] ?? 1 }}" required data-line-qty>
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="lines[{{ $index }}][unit_price]"
               class="form-control form-control-sm" data-line-price
               value="{{ $line['unit_price'] ?? 0 }}" required>
    </td>
    <td>
        <div class="input-group input-group-sm">
            <select name="lines[{{ $index }}][discount_mode]" class="form-select" data-line-discount-mode style="max-width: 5.5rem">
                <option value="amount" @selected($mode === 'amount')>{{ __('gross.fields.discount_as_amount_short') }}</option>
                <option value="percent" @selected($mode === 'percent')>%</option>
            </select>
            <input type="number" step="0.01" min="0" name="lines[{{ $index }}][discount_amount]"
                   class="form-control @if ($mode !== 'amount') d-none @endif" data-line-discount-amount
                   value="{{ $line['discount_amount'] ?? 0 }}"
                   @if ($mode !== 'amount') disabled @endif>
            <input type="number" step="0.01" min="0" max="100" name="lines[{{ $index }}][discount_percent]"
                   class="form-control @if ($mode !== 'percent') d-none @endif" data-line-discount-percent
                   value="{{ $line['discount_percent'] ?? 0 }}"
                   @if ($mode !== 'percent') disabled @endif>
        </div>
    </td>
    <td class="col-num fw-semibold"><span class="num-ltr" data-line-total>0.00</span></td>
    <td class="text-end">
        <button type="button" class="btn btn-link btn-sm text-danger p-0" data-line-remove title="{{ __('general.delete') }}">
            <i class="bi bi-trash" aria-hidden="true"></i>
            <span class="visually-hidden">{{ __('general.delete') }}</span>
        </button>
    </td>
</tr>
