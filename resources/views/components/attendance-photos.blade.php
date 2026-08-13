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
        <div class="d-flex flex-wrap gap-2">
            @foreach ($collection as $photo)
                <a href="{{ route('attendance.photos.show', $photo) }}"
                   target="_blank"
                   rel="noopener"
                   class="attendance-photo-thumb border rounded overflow-hidden bg-body-secondary"
                   title="{{ $photo->original_name ?: $label }}">
                    <img src="{{ route('attendance.photos.show', $photo) }}"
                         alt="{{ $photo->original_name ?: $label }}"
                         loading="lazy"
                         class="attendance-photo-img">
                </a>
            @endforeach
        </div>
    </div>
@endif
