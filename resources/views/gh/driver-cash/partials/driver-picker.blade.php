@php
    $selectedId = (int) ($selectedId ?? 0);
@endphp
<form method="get" action="{{ route('gh.driver-cash.index') }}" class="row g-2 align-items-end" data-gh-driver-picker>
    <div class="col-12 col-md-4">
        <label class="form-label" for="driver_search">{{ __('gh.driver_cash.search_driver') }}</label>
        <input id="driver_search" type="search" class="form-control form-control-sm" autocomplete="off"
               placeholder="{{ __('gh.driver_cash.search_driver') }}" data-gh-driver-filter>
    </div>
    <div class="col-12 col-md-5">
        <label class="form-label" for="driver_id">{{ __('gh.driver_cash.select_driver') }}</label>
        <select id="driver_id" name="driver_id" class="form-select form-select-sm" required>
            <option value="">—</option>
            @foreach ($driverOptions as $d)
                <option value="{{ $d->id }}" @selected($selectedId === (int) $d->id)>{{ $d->localizedName() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.driver_cash.open_dashboard') }}</button>
    </div>
</form>
@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-gh-driver-picker]').forEach(function (form) {
                var input = form.querySelector('[data-gh-driver-filter]');
                var select = form.querySelector('select');
                input.addEventListener('input', function () {
                    var term = input.value.trim().toLowerCase();
                    Array.prototype.forEach.call(select.options, function (opt) {
                        opt.hidden = opt.value !== '' && term !== '' && opt.text.toLowerCase().indexOf(term) === -1;
                    });
                });
            });
        </script>
    @endpush
@endonce
