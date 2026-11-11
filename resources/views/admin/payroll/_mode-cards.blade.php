{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('payroll.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('payroll.subtitle') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            @if (session('success') || session('error'))
                @if (session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-warning" role="status">{{ session('error') }}</div>
                @endif
            @endif
            <form method="GET" action="{{ route('admin.payroll.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-6 col-md-3">
                    <label for="month" class="form-label">{{ __('payroll.fields.month') }}</label>
                    <select id="month" name="month" class="form-select" onchange="this.form.submit()">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $month === $m)>
                                {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="year" class="form-label">{{ __('payroll.fields.year') }}</label>
                    <select id="year" name="year" class="form-select" onchange="this.form.submit()">
                        @for ($y = now()->year; $y >= now()->year - 2; $y--)
                            <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">{{ __('payroll.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('general.all') }}</option>
                        @foreach (['DRAFT', 'APPROVED', 'PAID'] as $st)
                            <option value="{{ $st }}" @selected($status === $st)>
                                {{ __('payroll.status.'.$st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.payroll.generate') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-play-circle me-1" aria-hidden="true"></i>
                        {{ __('payroll.generate') }}
                    </button>
                </div>
                <div class="col-6 col-md-auto">
                    <a href="{{ route('admin.payroll.export.xlsx', ['month' => $month, 'year' => $year]) }}"
                       class="btn btn-outline-secondary w-100">
                        {{ __('payroll.export_excel') }}
                    </a>
                </div>
                @if (! empty($exportReady))
                    <div class="col-6 col-md-auto">
                        <a href="{{ route('admin.payroll.export.download', ['month' => $month, 'year' => $year]) }}"
                           class="btn btn-outline-success w-100">
                            {{ __('payroll.download_excel') }}
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @php
        $periodLabel = \Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y');
    @endphp

    <div class="eval-card-ui" data-eval-card-ui="hr-payroll">
        <h2 class="h6 mb-2" style="color: var(--gh-board-navy)">{{ __('payroll.list') }}</h2>
        @if ($records->isEmpty())
            <div class="eval-card-ui__empty">
                <x-empty-state
                    :title="__('payroll.empty_title')"
                    :text="__('payroll.empty_text')"
                    icon="bi-cash-stack"
                />
            </div>
        @else
            <div data-table-filter-root>
                <div class="eval-card-ui__toolbar">
                    <x-table-toolbar
                        id="payroll-list"
                        :search-placeholder="__('payroll.fields.employee')"
                        :filters="[[
                            'name' => 'status',
                            'label' => __('payroll.fields.status'),
                            'options' => [
                                'DRAFT' => __('payroll.status.DRAFT'),
                                'APPROVED' => __('payroll.status.APPROVED'),
                                'PAID' => __('payroll.status.PAID'),
                            ],
                        ]]"
                    />
                </div>
                <div class="eval-card-ui__grid">
                    @foreach ($records as $row)
                        @php
                            $emp = $row->user;
                            $name = $emp
                                ? (app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en)
                                : __('general.not_available');
                            $photoUrl = ($emp?->profile?->personal_photo_path && auth()->user()?->can('view', $emp))
                                ? route('employees.photo', $emp)
                                : null;
                            $initial = mb_substr($name, 0, 1);
                        @endphp
                        <div
                            data-filterable-item
                            data-filter-text="{{ $name }} {{ $emp?->name_ar }} {{ $emp?->name_en }}"
                            data-filter-status="{{ $row->status }}"
                            class="h-100"
                        >
                            <x-gh.record-card
                                :title="$name"
                                :title-href="route('admin.payroll.show', $row)"
                                :image-url="$photoUrl"
                                :image-alt="$name"
                                image-variant="circle"
                                :image-placeholder="$initial"
                            >
                                <x-slot:badges>
                                    <span @class([
                                        'gh-record-card__badge',
                                        'gh-record-card__badge--muted' => $row->status === 'DRAFT',
                                        'gh-record-card__badge--status' => in_array($row->status, ['APPROVED', 'PAID'], true),
                                    ])>
                                        {{ __('payroll.status.'.$row->status) }}
                                    </span>
                                </x-slot:badges>
                                <x-slot:meta>
                                    <x-gh.meta-cell icon="bi-calendar3" :value="$periodLabel" />
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash"></i></span>
                                        <span class="gh-record-card__value"><x-money :amount="$row->gross_pay" :currency="$row->currency" /></span>
                                    </div>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-dash-circle"></i></span>
                                        <span class="gh-record-card__value"><x-money :amount="$row->total_deductions" :currency="$row->currency" /></span>
                                    </div>
                                    <div class="gh-record-card__cell">
                                        <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                        <span class="gh-record-card__value fw-bold"><x-money :amount="$row->net_pay" :currency="$row->currency" /></span>
                                    </div>
                                </x-slot:meta>
                                <x-slot:actions>
                                    <a href="{{ route('admin.payroll.show', $row) }}" class="gh-board-btn">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                        <span>{{ __('payroll.review') }}</span>
                                    </a>
                                    @if ($row->status === 'DRAFT')
                                        <form method="POST" action="{{ route('admin.payroll.approve', $row) }}" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="gh-board-btn gh-board-btn--ghost">
                                                <i class="bi bi-check2" aria-hidden="true"></i>
                                                <span>{{ __('payroll.approve') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </x-slot:actions>
                            </x-gh.record-card>
                        </div>
                    @endforeach
                    <div data-table-empty-msg class="d-none eval-card-ui__empty text-body-secondary text-center">
                        {{ __('general.no_filter_results') }}
                    </div>
                </div>
            </div>
            <div class="eval-card-ui__pager">{{ $records->links() }}</div>
        @endif
    </div>
