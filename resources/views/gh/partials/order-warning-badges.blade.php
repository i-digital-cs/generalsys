@php
    /** @var list<array{code?: string, tone?: string, label?: string}> $warnings */
    $warnings = is_array($warnings ?? null) ? $warnings : [];
@endphp
@foreach ($warnings as $warning)
    @php
        $tone = in_array(($warning['tone'] ?? ''), ['orange', 'red'], true)
            ? $warning['tone']
            : 'orange';
        $label = (string) ($warning['label'] ?? '');
        $code = (string) ($warning['code'] ?? '');
    @endphp
    @if ($label !== '')
        <span
            class="gh-warning-badge gh-warning-badge--{{ $tone }}{{ ! empty($compact) ? ' gh-warning-badge--compact' : '' }}"
            @if ($code !== '') data-warning-code="{{ $code }}" @endif
            title="{{ $label }}"
        >{{ $label }}</span>
    @endif
@endforeach
