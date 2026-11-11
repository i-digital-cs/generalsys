{{-- Modern card presentation (working tree) --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('advances.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('advances.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.advances.create') }}" class="btn btn-primary">
            {{ __('advances.create') }}
        </a>
    </div>

    <div class="eval-card-ui" data-eval-card-ui="hr-advances">
        <div class="card gh-board-filters border-0">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.advances.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label for="user_id" class="form-label mb-1">{{ __('advances.fields.employee') }}</label>
                        <select id="user_id" name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach ($employees as $emp)
                                @php
                                    $label = app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en;
                                @endphp
                                <option value="{{ $emp->id }}" @selected((int) ($filters['user_id'] ?? 0) === $emp->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="status" class="form-label mb-1">{{ __('advances.fields.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">{{ __('general.all') }}</option>
                            @foreach (['ACTIVE', 'FULLY_DEDUCTED'] as $st)
                                <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>
                                    {{ __('advances.status.'.$st) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>

        @if ($advances->isEmpty())
            <div class="eval-card-ui__empty">
                <x-empty-state
                    :title="__('advances.empty_title')"
                    :text="__('advances.empty_text')"
                    icon="bi-cash-coin"
                />
            </div>
        @else
            <div class="eval-card-ui__grid">
                @foreach ($advances as $row)
                    @php
                        $emp = $row->user;
                        $name = $emp
                            ? (app()->getLocale() === 'ar' ? $emp->name_ar : $emp->name_en)
                            : __('general.not_available');
                        $currency = $emp->currency ?? 'EGP';
                        $photoUrl = ($emp?->profile?->personal_photo_path && auth()->user()?->can('view', $emp))
                            ? route('employees.photo', $emp)
                            : null;
                        $initial = mb_substr($name, 0, 1);
                    @endphp
                    <x-gh.record-card
                        :title="$name"
                        :title-href="$row->status === 'ACTIVE' ? route('admin.advances.edit', $row) : null"
                        :image-url="$photoUrl"
                        :image-alt="$name"
                        image-variant="circle"
                        :image-placeholder="$initial"
                    >
                        <x-slot:badges>
                            <span @class([
                                'gh-record-card__badge',
                                'gh-record-card__badge--status' => $row->status === 'ACTIVE',
                                'gh-record-card__badge--muted' => $row->status === 'FULLY_DEDUCTED',
                            ])>
                                {{ __('advances.status.'.$row->status) }}
                            </span>
                        </x-slot:badges>
                        <x-slot:meta>
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->amount" :currency="$currency" /></span>
                            </div>
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-calendar-month"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->deduction_amount_monthly" :currency="$currency" /></span>
                            </div>
                            <div class="gh-record-card__cell">
                                <span class="gh-record-card__icon" aria-hidden="true"><i class="bi bi-piggy-bank"></i></span>
                                <span class="gh-record-card__value"><x-money :amount="$row->remaining_amount" :currency="$currency" /></span>
                            </div>
                            <x-gh.meta-cell icon="bi-calendar3" :value="$row->date_given->translatedFormat('d M Y')" />
                        </x-slot:meta>
                        <x-slot:actions>
                            @if ($row->status === 'ACTIVE')
                                <a href="{{ route('admin.advances.edit', $row) }}" class="gh-board-btn">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    <span>{{ __('general.edit') }}</span>
                                </a>
                            @endif
                        </x-slot:actions>
                    </x-gh.record-card>
                @endforeach
            </div>
            <div class="eval-card-ui__pager">{{ $advances->links() }}</div>
        @endif
    </div>
