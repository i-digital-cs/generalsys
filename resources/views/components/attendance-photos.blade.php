@props([
    'photos' => null,
    'kind' => null,
    'compact' => false,
])

@php
    use App\Models\AttendancePhoto;

    $collection = collect($photos ?? []);
    if ($kind !== null) {
        $collection = $collection->where('kind', $kind)->values();
    }

    $label = match ($kind) {
        AttendancePhoto::KIND_CLOCK_IN => __('clock.photos.in_label'),
        AttendancePhoto::KIND_CLOCK_OUT => __('clock.photos.out_label'),
        default => __('clock.photos.title'),
    };
@endphp

@if ($collection->isNotEmpty())
    <div {{ $attributes->class(['attendance-photos', $compact ? 'attendance-photos--compact' : '']) }}>
        @unless ($compact)
            <div class="small fw-semibold mb-1">{{ $label }}</div>
        @endunless
        <div class="d-flex flex-wrap gap-3">
            @foreach ($collection as $photo)
                @php
                    $caption = trim((string) ($photo->description ?? ''));
                    $title = $caption !== '' ? $caption : ($photo->original_name ?: $label);
                @endphp
                <figure class="mb-0" style="max-width: 9rem;">
                    <a href="{{ route('attendance.photos.show', $photo) }}"
                       target="_blank"
                       rel="noopener"
                       class="attendance-photo-thumb border rounded overflow-hidden bg-body-secondary d-block"
                       title="{{ $title }}">
                        <img src="{{ route('attendance.photos.show', $photo) }}"
                             alt="{{ $title }}"
                             loading="lazy"
                             class="attendance-photo-img">
                    </a>
                    @if ($caption !== '')
                        <figcaption class="small text-body-secondary mt-1 text-break">{{ $caption }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
@endif
