@php
    /** @var array<string, mixed> $card */
    /** @var string $role */
    $role = $role ?? 'driver';
    $statusModifier = match ($card['status']) {
        'LATER' => 'later',
        'PICKUP' => 'pickup',
        'WORKING' => 'working',
        'DELIVERY' => 'delivery',
        'COMPLETED' => 'completed',
        'CANCELLED' => 'cancelled',
        default => 'unknown',
    };
    $kindLabel = match ($card['schedule_kind'] ?? 'pickup') {
        'delivery' => __('gh.board.appointment_delivery'),
        'working' => __('gh.board.appointment_working'),
        default => __('gh.board.appointment_pickup'),
    };
    $kindIcon = match ($card['schedule_kind'] ?? 'pickup') {
        'delivery' => 'bi-truck',
        'working' => 'bi-hourglass-split',
        default => 'bi-box-seam',
    };
    $canExecute = $role === 'driver' && ! empty($card['can_execute_task']);
    $taskLabel = (string) ($card['task_action_label'] ?? __('gh.board.view'));
@endphp
<article
    class="gh-order-card {{ ! empty($card['overdue']) ? 'is-overdue' : '' }} {{ ! empty($card['read_only']) ? 'is-readonly' : '' }}"
    data-order-id="{{ $card['id'] }}"
    data-gh-order-card
>
    <header class="gh-order-card__head">
        <a href="{{ $card['href'] }}" class="gh-order-card__ref num-ltr">{{ $card['reference'] }}</a>
        <div class="gh-order-card__badges">
            <span class="gh-order-card__badge gh-status-badge gh-status-badge--{{ $statusModifier }}">{{ $card['status_label'] }}</span>
            @include('gh.partials.order-warning-badges', [
                'warnings' => $card['warnings'] ?? [],
                'compact' => true,
            ])
            @if (! empty($card['invoice_status_label']))
                <span class="gh-order-card__badge" title="{{ __('gh.orders.invoice_status') }}">{{ $card['invoice_status_label'] }}</span>
            @endif
            @if (! empty($card['delivery_transition_label']))
                <span class="gh-order-card__badge" title="{{ __('gh.board.delivery_transition_hint') }}">{{ $card['delivery_transition_label'] }}</span>
            @endif
        </div>
    </header>

    {{-- Upper: 3 inline [icon][value] groups — seq fixed, customer/address flex --}}
    <div class="gh-order-card__meta gh-order-card__meta--3" data-gh-card-upper role="group" aria-label="{{ __('gh.board.sequence') }}">
        <div class="gh-order-card__cell gh-order-card__cell--seq">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-list-ol"></i></span>
            <span class="gh-order-card__value num-ltr" title="#{{ $card['sequence'] }}">#{{ $card['sequence'] }}</span>
        </div>
        <div class="gh-order-card__cell gh-order-card__cell--customer">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-person"></i></span>
            <span class="gh-order-card__value" title="{{ $card['customer_name'] }}">{{ $card['customer_name'] }}</span>
        </div>
        <div class="gh-order-card__cell gh-order-card__cell--address">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
            <span class="gh-order-card__value" title="{{ $card['address_line'] }}">{{ $card['address_line'] }}</span>
        </div>
    </div>

    {{-- Lower: dedicated appointment row (date+time grouped) + quiet secondary meta --}}
    @php
        $notScheduled = in_array($card['schedule_kind'] ?? '', ['delivery', 'working'], true)
            && ! empty($card['delivery_not_scheduled']);
        $appointmentWhen = $notScheduled
            ? null
            : trim(($card['scheduled_date'] ?? '').' — '.($card['scheduled_time'] ?? ''));
        $appointmentTitle = match ($card['schedule_kind'] ?? 'pickup') {
            'delivery', 'working' => ($card['delivery_scheduled_full'] ?? __('gh.orders.delivery_not_scheduled')),
            default => ($card['pickup_scheduled_full'] ?? ($card['scheduled_date_full'] ?? '')),
        };
    @endphp
    <div class="gh-order-card__lower" data-gh-card-lower>
        <div class="gh-order-card__appointment" data-gh-card-appointment title="{{ $appointmentTitle }}">
            <div class="gh-order-card__appointment-main">
                <span class="gh-order-card__icon" aria-hidden="true"><i class="bi {{ $kindIcon }}"></i></span>
                <span class="gh-order-card__appointment-label">{{ $kindLabel }}:</span>
                @if ($notScheduled)
                    <span class="gh-order-card__appointment-when">{{ __('gh.orders.delivery_not_scheduled') }}</span>
                @else
                    <span class="gh-order-card__appointment-when num-ltr">{{ $appointmentWhen ?: '—' }}</span>
                @endif
            </div>
            @if (! $notScheduled && ($card['scheduled_time'] ?? '—') !== '—')
                <div class="gh-order-card__appointment-tz">
                    <span class="gh-uae-badge gh-uae-badge--board">
                        <span class="gh-uae-flag" aria-hidden="true">{{ __('gh.time.uae_flag') }}</span>
                        <span>{{ __('gh.time.uae_time_label') }}</span>
                    </span>
                </div>
            @endif
        </div>
        <div class="gh-order-card__meta gh-order-card__meta--2" role="group">
            <div class="gh-order-card__cell gh-order-card__cell--created">
                <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                <span class="gh-order-card__value num-ltr" title="{{ __('gh.orders.created_at') }}: {{ $card['created_at_full'] ?? '—' }}">{{ $card['created_at_short'] ?? '—' }}</span>
            </div>
            <div class="gh-order-card__cell gh-order-card__cell--type">
                <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-tag"></i></span>
                <span class="gh-order-card__value" title="{{ __('gh.orders.actual_pickup') }}: {{ $card['pickup_actual_full'] ?? '—' }}">{{ $card['order_type_label'] }}</span>
            </div>
        </div>
    </div>

    <footer class="gh-order-card__actions">
        <div class="gh-order-card__action-btns">
            @if ($canExecute)
                <a href="{{ $card['href'] }}" class="gh-board-btn" data-gh-task-action>
                    <i class="bi {{ ($card['task_type'] ?? '') === 'delivery' ? 'bi-truck' : 'bi-box-seam' }}" aria-hidden="true"></i>
                    <span>{{ $taskLabel }}</span>
                </a>
                <a href="{{ $card['href'] }}" class="gh-board-btn gh-board-btn--ghost">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    <span>{{ __('gh.board.view') }}</span>
                </a>
            @else
                <a href="{{ $card['href'] }}" class="gh-board-btn">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    <span>{{ __('gh.board.view') }}</span>
                </a>
            @endif
            @if ($role === 'driver' && ! empty($card['maps_url']) && empty($card['read_only']))
                <a href="{{ $card['maps_url'] }}" class="gh-board-btn gh-board-btn--ghost" target="_blank" rel="noopener">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <span>{{ __('gh.driver.open_maps') }}</span>
                </a>
            @endif
            @if ($role === 'admin' && ! empty($card['can_edit']))
                <a href="{{ route('gh.orders.edit', $card['id']) }}" class="gh-board-btn gh-board-btn--ghost">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    <span>{{ __('gh.board.edit') }}</span>
                </a>
            @endif
        </div>
        @if ((! empty($card['pickup_driver']) || ! empty($card['delivery_driver']) || ! empty($card['needs_delivery_driver']))
            && ($role === 'admin' || ($role === 'driver' && ! empty($card['needs_delivery_driver']))))
            <div class="gh-order-card__drivers">
                @if ($role === 'admin' && ! empty($card['pickup_driver']))
                    <span title="{{ __('gh.orders.pickup_driver') }}">
                        <i class="bi bi-person-up" aria-hidden="true"></i>
                        <span class="gh-order-card__driver-name">{{ $card['pickup_driver'] }}</span>
                    </span>
                @endif
                @if (! empty($card['delivery_driver']))
                    <span title="{{ __('gh.orders.delivery_driver') }}">
                        <i class="bi bi-person-down" aria-hidden="true"></i>
                        <span class="gh-order-card__driver-name">{{ $card['delivery_driver'] }}</span>
                    </span>
                @elseif (! empty($card['needs_delivery_driver']))
                    <span title="{{ __('gh.orders.delivery_driver') }}">
                        <i class="bi bi-person-down" aria-hidden="true"></i>
                        <span class="gh-order-card__driver-name">{{ __('gh.orders.unassigned') }}</span>
                    </span>
                @endif
            </div>
        @endif
    </footer>
</article>
