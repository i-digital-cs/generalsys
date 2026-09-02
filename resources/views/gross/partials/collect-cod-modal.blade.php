@props([
    'invoice',
    'treasuryAccounts' => collect(),
    'canPostTreasury' => false,
    'modalId' => null,
])

@php
    $modalId = $modalId ?? 'gross-collect-cod-modal-'.$invoice->id;
    $defaultAccountId = $treasuryAccounts->first()?->id;
@endphp

<button type="button"
        class="btn btn-success btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}">
    {{ __('gross.sales.collect_cod') }}
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="{{ route('gross.sales.collect-cod', $invoice) }}" class="modal-content">
            @csrf
            <div class="modal-header py-2">
                <h2 class="modal-title h6 mb-0" id="{{ $modalId }}-title">{{ __('gross.sales.collect_cod') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('general.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary mb-3">
                    {{ __('gross.sales.collect_cod_help', ['amount' => number_format($invoice->collectibleAmount(), 2, '.', '')]) }}
                </p>
                @if ($canPostTreasury && $treasuryAccounts->isNotEmpty())
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="post_to_treasury" value="1"
                               id="{{ $modalId }}-post" checked>
                        <label class="form-check-label" for="{{ $modalId }}-post">
                            {{ __('gross.sales.post_to_treasury') }}
                        </label>
                    </div>
                    <div class="mb-0" data-treasury-panel>
                        <label class="form-label" for="{{ $modalId }}-account">{{ __('gross.treasury.account') }}</label>
                        <select id="{{ $modalId }}-account" name="treasury_account_id" class="form-select form-select-sm" required>
                            @foreach ($treasuryAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) $defaultAccountId === (string) $account->id)>
                                    {{ __('gross.treasury.kinds.'.$account->kind) }} — {{ $account->name }} ({{ $account->currency }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('general.cancel') }}</button>
                <button type="submit" class="btn btn-success btn-sm">{{ __('gross.sales.collect_cod') }}</button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        document.querySelectorAll('[data-treasury-panel]').forEach(function (panel) {
            var modal = panel.closest('.modal');
            if (!modal) return;
            var checkbox = modal.querySelector('input[name="post_to_treasury"]');
            var select = panel.querySelector('select[name="treasury_account_id"]');
            if (!checkbox || !select) return;
            function sync() {
                var on = checkbox.checked;
                select.required = on;
                panel.classList.toggle('d-none', !on);
            }
            checkbox.addEventListener('change', sync);
            sync();
        });
    })();
    </script>
    @endpush
@endonce
