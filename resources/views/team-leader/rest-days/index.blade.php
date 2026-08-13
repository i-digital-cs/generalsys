@extends('layouts.app')

@section('title', __('employees.rest_days.tl_title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('employees.rest_days.tl_title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('employees.rest_days.tl_subtitle') }}</p>
        </div>
    </div>

    @error('date')
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror
    @error('dates')
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror
    @error('dates.*')
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    @if ($employees->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <x-empty-state
                    :title="__('dashboard.hours.no_team_title')"
                    :text="__('dashboard.hours.no_team_text')"
                    icon="bi-calendar-week"
                />
            </div>
        </div>
    @else
        <div data-table-filter-root>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3">
                    <x-table-toolbar
                        id="rest-days-team"
                        :search-placeholder="__('dashboard.hours.search_name')"
                        class="mb-0"
                    />
                </div>
            </div>

            <div class="row g-3">
                @foreach ($employees as $member)
                    @php
                        $name = app()->getLocale() === 'ar' ? $member->name_ar : $member->name_en;
                        [$past, $upcoming] = $member->restDays->partition(
                            fn ($restDay) => $restDay->date->startOfDay()->lt(today())
                        );
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4" data-filterable-item
                         data-filter-text="{{ $name }} {{ $member->name_ar }} {{ $member->name_en }}">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary fw-semibold"
                                          style="width: 2.25rem; height: 2.25rem;" aria-hidden="true">
                                        {{ mb_substr($name, 0, 1) }}
                                    </span>
                                    <h2 class="h6 mb-0 text-truncate">{{ $name }}</h2>
                                    <span class="badge text-bg-secondary ms-auto">
                                        {{ $upcoming->count() }} {{ __('employees.rest_days.upcoming') }}
                                    </span>
                                </div>

                                <form method="POST" action="{{ route('team-leader.rest-days.store', $member) }}"
                                      class="mb-3" data-rest-multi
                                      data-locale="{{ app()->getLocale() }}"
                                      data-existing='@json($member->restDays->map(fn ($d) => $d->date->toDateString())->values())'>
                                    @csrf
                                    <div class="fw-semibold small mb-1">{{ __('employees.rest_days.fields.dates') }}</div>
                                    <div class="form-text mb-2">{{ __('employees.rest_days.multi_hint') }}</div>

                                    <div class="rest-multi-picker border rounded p-2 bg-body mb-2">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-rest-prev aria-label="{{ __('employees.rest_days.prev_month') }}">
                                                <i class="bi bi-chevron-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}" aria-hidden="true"></i>
                                            </button>
                                            <div class="fw-semibold small mb-0 text-center" data-rest-month-label></div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-rest-next aria-label="{{ __('employees.rest_days.next_month') }}">
                                                <i class="bi bi-chevron-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }}" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        <div class="rest-multi-weekdays" data-rest-weekdays></div>
                                        <div class="rest-multi-grid" data-rest-cal role="grid" aria-label="{{ __('employees.rest_days.fields.dates') }}"></div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-1 mb-2" data-rest-chips></div>
                                    <div class="small text-danger d-none mb-2" data-rest-pick-err></div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100" data-rest-submit disabled>
                                        <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>
                                        {{ __('employees.rest_days.add') }}
                                        <span class="badge text-bg-light text-primary ms-1 d-none" data-rest-count>0</span>
                                    </button>
                                </form>

                                @if ($member->restDays->isEmpty())
                                    <p class="small text-body-secondary mb-0">{{ __('employees.rest_days.empty') }}</p>
                                @else
                                    @if ($upcoming->isNotEmpty())
                                        <ul class="list-group list-group-flush small border-top">
                                            @foreach ($upcoming as $restDay)
                                                <li class="list-group-item px-0 d-flex align-items-center justify-content-between gap-2">
                                                    <span>
                                                        <span class="fw-semibold">{{ $restDay->date->translatedFormat('l') }}</span>
                                                        <span class="text-body-secondary">{{ $restDay->date->translatedFormat('j M Y') }}</span>
                                                        <span class="num-ltr text-body-secondary">{{ $restDay->date->toDateString() }}</span>
                                                    </span>
                                                    @if ($restDay->pendingReservation !== null)
                                                        <span class="badge text-bg-warning flex-shrink-0"
                                                              title="{{ __('employees.rest_days.reserved_title') }}">
                                                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                            {{ __('employees.rest_days.reserved') }}
                                                        </span>
                                                    @else
                                                        <form method="POST" class="flex-shrink-0"
                                                              action="{{ route('team-leader.rest-days.destroy', [$member, $restDay]) }}"
                                                              onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                {{ __('employees.rest_days.remove') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if ($past->isNotEmpty())
                                        <details class="small mt-auto pt-2 @if ($upcoming->isNotEmpty()) border-top @endif">
                                            <summary class="text-body-secondary">
                                                {{ __('employees.rest_days.past') }} ({{ $past->count() }})
                                            </summary>
                                            <ul class="list-group list-group-flush small">
                                                @foreach ($past as $restDay)
                                                    <li class="list-group-item px-0 d-flex align-items-center justify-content-between gap-2 text-body-secondary">
                                                        <span>
                                                            <span>{{ $restDay->date->translatedFormat('l') }}</span>
                                                            <span>{{ $restDay->date->translatedFormat('j M Y') }}</span>
                                                            <span class="num-ltr">{{ $restDay->date->toDateString() }}</span>
                                                        </span>
                                                        @if ($restDay->pendingReservation !== null)
                                                            <span class="badge text-bg-warning flex-shrink-0"
                                                                  title="{{ __('employees.rest_days.reserved_title') }}">
                                                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                                {{ __('employees.rest_days.reserved') }}
                                                            </span>
                                                        @else
                                                            <form method="POST" class="flex-shrink-0"
                                                                  action="{{ route('team-leader.rest-days.destroy', [$member, $restDay]) }}"
                                                                  onsubmit="return confirm(@json(__('employees.rest_days.remove').'?'))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    {{ __('employees.rest_days.remove') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div data-table-empty-msg class="d-none card border-0 shadow-sm mt-3">
                <div class="card-body text-body-secondary text-center py-4">
                    {{ __('general.no_filter_results') }}
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<style>
.rest-multi-weekdays,
.rest-multi-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: .2rem;
}
.rest-multi-weekday {
    font-size: .65rem;
    font-weight: 600;
    text-align: center;
    color: var(--bs-secondary-color);
    padding: .15rem 0;
}
.rest-multi-day {
    appearance: none;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    border-radius: .35rem;
    min-height: 2rem;
    font-size: .8rem;
    font-weight: 600;
    padding: 0;
    line-height: 1;
    cursor: pointer;
}
.rest-multi-day:hover:not(:disabled) {
    border-color: var(--bs-primary);
    color: var(--bs-primary);
}
.rest-multi-day.is-outside {
    visibility: hidden;
    pointer-events: none;
}
.rest-multi-day.is-picked {
    background: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff;
}
.rest-multi-day.is-existing {
    background: var(--bs-secondary-bg);
    color: var(--bs-secondary-color);
    cursor: not-allowed;
    text-decoration: line-through;
}
.rest-multi-day.is-today:not(.is-picked):not(.is-existing) {
    box-shadow: inset 0 0 0 1px var(--bs-primary);
}
</style>
<script>
(function () {
    const msgs = {
        needDate: @json(__('employees.rest_days.errors.pick_first')),
        max: @json(__('employees.rest_days.errors.pick_max', ['max' => 31])),
        remove: @json(__('employees.rest_days.pick_remove')),
        existing: @json(__('employees.rest_days.already_marked')),
    };
    const maxDates = 31;

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function ymd(year, monthIndex, day) {
        return year + '-' + pad(monthIndex + 1) + '-' + pad(day);
    }

    function todayYmd() {
        const now = new Date();
        return ymd(now.getFullYear(), now.getMonth(), now.getDate());
    }

    function parseExisting(form) {
        try {
            const raw = JSON.parse(form.getAttribute('data-existing') || '[]');
            return new Set(Array.isArray(raw) ? raw : []);
        } catch (e) {
            return new Set();
        }
    }

    function initForm(form) {
        const existing = parseExisting(form);
        const selected = new Set();
        const locale = form.getAttribute('data-locale') || 'en';
        const cal = form.querySelector('[data-rest-cal]');
        const weekdaysEl = form.querySelector('[data-rest-weekdays]');
        const labelEl = form.querySelector('[data-rest-month-label]');
        const chipsEl = form.querySelector('[data-rest-chips]');
        const errEl = form.querySelector('[data-rest-pick-err]');
        const submitBtn = form.querySelector('[data-rest-submit]');
        const countEl = form.querySelector('[data-rest-count]');
        const prevBtn = form.querySelector('[data-rest-prev]');
        const nextBtn = form.querySelector('[data-rest-next]');

        let cursor = new Date();
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), 1);

        function showErr(message) {
            if (!errEl) return;
            errEl.textContent = message || '';
            errEl.classList.toggle('d-none', !message);
        }

        function syncSubmit() {
            if (submitBtn) submitBtn.disabled = selected.size === 0;
            if (countEl) {
                countEl.textContent = String(selected.size);
                countEl.classList.toggle('d-none', selected.size === 0);
            }
        }

        function syncChips() {
            if (!chipsEl) return;
            chipsEl.innerHTML = '';
            Array.from(selected).sort().forEach(function (value) {
                const wrap = document.createElement('span');
                wrap.className = 'badge bg-primary-subtle text-primary border d-inline-flex align-items-center gap-1 pe-1';

                const label = document.createElement('span');
                label.className = 'num-ltr px-1';
                label.textContent = value;

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'dates[]';
                hidden.value = value;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-sm btn-link text-danger p-0 lh-1';
                remove.setAttribute('aria-label', msgs.remove);
                remove.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
                remove.addEventListener('click', function () {
                    selected.delete(value);
                    showErr('');
                    syncChips();
                    renderMonth();
                    syncSubmit();
                });

                wrap.appendChild(label);
                wrap.appendChild(hidden);
                wrap.appendChild(remove);
                chipsEl.appendChild(wrap);
            });
        }

        function renderWeekdays() {
            if (!weekdaysEl) return;
            weekdaysEl.innerHTML = '';
            // Monday-first labels
            for (let i = 0; i < 7; i++) {
                const d = new Date(Date.UTC(2021, 0, 4 + i)); // Mon 4 Jan 2021
                const el = document.createElement('div');
                el.className = 'rest-multi-weekday';
                el.textContent = d.toLocaleDateString(locale, { weekday: 'narrow', timeZone: 'UTC' });
                weekdaysEl.appendChild(el);
            }
        }

        function renderMonth() {
            if (!cal || !labelEl) return;
            const year = cursor.getFullYear();
            const month = cursor.getMonth();
            labelEl.textContent = cursor.toLocaleDateString(locale, { month: 'long', year: 'numeric' });

            const firstDow = (new Date(year, month, 1).getDay() + 6) % 7; // Mon=0
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const today = todayYmd();

            cal.innerHTML = '';
            for (let i = 0; i < firstDow; i++) {
                const blank = document.createElement('button');
                blank.type = 'button';
                blank.className = 'rest-multi-day is-outside';
                blank.tabIndex = -1;
                blank.disabled = true;
                cal.appendChild(blank);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const value = ymd(year, month, day);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'rest-multi-day';
                btn.textContent = String(day);
                btn.dataset.date = value;
                btn.setAttribute('aria-pressed', selected.has(value) ? 'true' : 'false');

                if (value === today) btn.classList.add('is-today');
                if (existing.has(value)) {
                    btn.classList.add('is-existing');
                    btn.disabled = true;
                    btn.title = msgs.existing;
                } else if (selected.has(value)) {
                    btn.classList.add('is-picked');
                }

                btn.addEventListener('click', function () {
                    if (existing.has(value)) return;
                    if (selected.has(value)) {
                        selected.delete(value);
                    } else {
                        if (selected.size >= maxDates) {
                            showErr(msgs.max);
                            return;
                        }
                        selected.add(value);
                    }
                    showErr('');
                    syncChips();
                    renderMonth();
                    syncSubmit();
                });

                cal.appendChild(btn);
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1);
                renderMonth();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
                renderMonth();
            });
        }

        form.addEventListener('submit', function (event) {
            if (selected.size === 0) {
                event.preventDefault();
                showErr(msgs.needDate);
            }
        });

        renderWeekdays();
        renderMonth();
        syncChips();
        syncSubmit();
    }

    document.querySelectorAll('[data-rest-multi]').forEach(initForm);
})();
</script>
@endpush
