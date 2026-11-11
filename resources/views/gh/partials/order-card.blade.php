@php
    /** @var array<string, mixed> $card */
    /** @var string $role */
    $role = $role ?? 'driver';
    $statusClass = match ($card['status']) {
        'COMPLETED' => 'is-done',
        'WORKING' => 'is-working',
        default => '',
    };
    $kindLabel = $card['schedule_kind'] === 'delivery'
        ? __('gh.board.kind_delivery')
        : __('gh.board.kind_pickup');
    $kindIcon = $card['schedule_kind'] === 'delivery' ? 'bi-truck' : 'bi-box-seam';
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
            @if (! empty($card['overdue']))
                <span
                    class="gh-order-card__badge gh-order-card__badge--danger"
                    title="{{ __('gh.board.overdue_hint') }}"
                    aria-label="{{ __('gh.board.overdue_hint') }}"
                >{{ __('gh.driver.overdue') }}</span>
            @endif
            <span class="gh-order-card__badge gh-order-card__badge--status {{ $statusClass }}">{{ $card['status_label'] }}</span>
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

    {{-- Lower: 4 inline [icon][value] groups — date/time/type stay fully readable --}}
    <div class="gh-order-card__meta gh-order-card__meta--4" data-gh-card-lower role="group">
        <div class="gh-order-card__cell gh-order-card__cell--date">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
            <span class="gh-order-card__value num-ltr" title="{{ $card['scheduled_date_full'] ?? $card['scheduled_date'] }}">{{ $card['scheduled_date'] }}</span>
        </div>
        <div class="gh-order-card__cell gh-order-card__cell--time">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
            <span class="gh-order-card__value num-ltr" title="{{ $card['scheduled_time'] }}">{{ $card['scheduled_time'] }}</span>
        </div>
        <div class="gh-order-card__cell gh-order-card__cell--kind">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi {{ $kindIcon }}"></i></span>
            <span class="gh-order-card__value" title="{{ $kindLabel }}">{{ $kindLabel }}</span>
        </div>
        <div class="gh-order-card__cell gh-order-card__cell--type">
            <span class="gh-order-card__icon" aria-hidden="true"><i class="bi bi-tag"></i></span>
            <span class="gh-order-card__value" title="{{ $card['order_type_label'] }}">{{ $card['order_type_label'] }}</span>
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
        @if ($role === 'admin' && (! empty($card['pickup_driver']) || ! empty($card['delivery_driver'])))
            <div class="gh-order-card__drivers">
                @if (! empty($card['pickup_driver']))
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
                @endif
            </div>
        @endif
    </footer>
</article>
