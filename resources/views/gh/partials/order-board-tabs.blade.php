@php
    /** @var list<array{key: string, label: string, count: int}> $tabs */
    /** @var string $activeTab */
    /** @var string $baseUrl */
    $query = request()->except(['tab', 'page']);
@endphp
<nav class="gh-board-tabs mb-3" aria-label="{{ __('gh.board.tabs_aria') }}">
    <div class="gh-board-tabs-scroll">
        @foreach ($tabs as $tab)
            @php
                $url = $baseUrl.'?'.http_build_query(array_merge($query, ['tab' => $tab['key']]));
                $isActive = $activeTab === $tab['key'];
            @endphp
            <a href="{{ $url }}"
               class="gh-board-tab {{ $isActive ? 'is-active' : '' }}"
               @if ($isActive) aria-current="page" @endif>
                <span class="gh-board-tab-label">{{ $tab['label'] }}</span>
                <span class="gh-board-tab-count num-ltr">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
