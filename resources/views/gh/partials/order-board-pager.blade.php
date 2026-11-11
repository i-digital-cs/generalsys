@php
    /** @var array{page: int, per_page: int, total: int, last_page: int, from: int, to: int}|null $pager */
    $pager = $pager ?? null;
@endphp
@if ($pager !== null && (int) $pager['total'] > (int) $pager['per_page'])
    @php
        $query = request()->except(['page']);
        $prev = max(1, (int) $pager['page'] - 1);
        $next = min((int) $pager['last_page'], (int) $pager['page'] + 1);
        $base = $baseUrl ?? url()->current();
        $prevUrl = $base.'?'.http_build_query(array_merge($query, ['page' => $prev]));
        $nextUrl = $base.'?'.http_build_query(array_merge($query, ['page' => $next]));
    @endphp
    <nav class="gh-board-pager" aria-label="{{ __('gh.board.pager_aria') }}">
        <div class="num-ltr">
            {{ __('gh.board.pager_range', [
                'from' => $pager['from'],
                'to' => $pager['to'],
                'total' => $pager['total'],
            ]) }}
        </div>
        <div class="gh-board-pager-actions">
            @if ((int) $pager['page'] > 1)
                <a class="gh-board-btn gh-board-btn--ghost" href="{{ $prevUrl }}">{{ __('gh.board.prev') }}</a>
            @else
                <span class="gh-board-btn gh-board-btn--ghost" aria-disabled="true" style="opacity:.45">{{ __('gh.board.prev') }}</span>
            @endif
            @if ((int) $pager['page'] < (int) $pager['last_page'])
                <a class="gh-board-btn" href="{{ $nextUrl }}">{{ __('gh.board.next') }}</a>
            @else
                <span class="gh-board-btn" aria-disabled="true" style="opacity:.45">{{ __('gh.board.next') }}</span>
            @endif
        </div>
    </nav>
@endif
