@if (! empty($breadcrumbs))
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            @foreach ($breadcrumbs as $crumb)
                @if ($loop->last)
                    <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                @endif
            @endforeach
        </ol>
    </nav>
@endif
