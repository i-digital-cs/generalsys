@php
    /** @var list<array{key: string, name: string, count: int, orders: list<array<string, mixed>>}> $groups */
    /** @var string $role */
    /** @var bool $sortable */
    $sortable = $sortable ?? false;
    $role = $role ?? 'driver';
@endphp

@if (count($groups) === 0)
    <x-empty-state :title="__('gh.board.empty')" icon="bi-clipboard-check" :text="__('gh.board.empty_hint')" />
@else
    {{--
      V2.4.1: SortableJS must own a container whose *direct* children are [data-sort-item].
      Area grouping nests cards, so each .gh-board-area-list is its own sortable scope
      (no cross-area moves). Persistence sends the full board DOM order.
    --}}
    <div class="gh-board-groups" @if ($sortable) data-gh-board-sort-root data-reorder-url="{{ $reorderUrl ?? '' }}" data-error-message="{{ __('gh.driver.reorder_failed') }}" @endif>
        @foreach ($groups as $group)
            <section class="gh-board-area" data-area-key="{{ $group['key'] }}">
                <header class="gh-board-area-head">
                    <h2 class="gh-board-area-title">{{ $group['name'] }}</h2>
                    <span class="gh-board-area-count num-ltr" aria-label="{{ __('gh.board.area_count', ['count' => $group['count']]) }}">{{ $group['count'] }}</span>
                </header>
                <div
                    class="gh-board-area-list"
                    @if ($sortable)
                        data-gh-sortable
                        data-reorder-url="{{ $reorderUrl ?? '' }}"
                        data-error-message="{{ __('gh.driver.reorder_failed') }}"
                    @endif
                >
                    @foreach ($group['orders'] as $card)
                        <div class="gh-board-area-item"
                             @if ($sortable) data-sort-item data-order-id="{{ $card['id'] }}" @endif>
                            @if ($sortable)
                                <div class="gh-board-sort-controls">
                                    <button type="button" class="gh-board-sort-btn" data-sort-up
                                            aria-label="{{ __('gh.driver.move_up') }}">
                                        <i class="bi bi-chevron-up" aria-hidden="true"></i>
                                    </button>
                                    <span class="hp-driver-sort-handle" data-sort-handle role="button" tabindex="0"
                                          title="{{ __('gh.driver.sort_handle') }}"
                                          aria-label="{{ __('gh.driver.sort_handle') }}">
                                        <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                    </span>
                                    <span class="gh-board-sort-pos num-ltr" data-sort-position>{{ $card['sequence'] }}</span>
                                    <button type="button" class="gh-board-sort-btn" data-sort-down
                                            aria-label="{{ __('gh.driver.move_down') }}">
                                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endif
                            <div class="gh-board-area-item__body min-w-0">
                                @include('gh.partials.order-card', ['card' => $card, 'role' => $role])
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
