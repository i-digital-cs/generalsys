@props([
    'searchPlaceholder' => null,
    'filters' => [],
    'id' => null,
])

@php
    $toolbarId = $id ?? 'tbl-'.uniqid();
    $searchPlaceholder = $searchPlaceholder ?? __('general.search_placeholder');
@endphp

<div {{ $attributes->class(['table-toolbar row g-2 align-items-end mb-3']) }} data-table-toolbar>
    <div class="col-12 col-md">
        <label class="form-label" for="{{ $toolbarId }}-search">
            {{ __('general.search') }}
        </label>
        <div class="input-group">
            <span class="input-group-text" aria-hidden="true">
                <i class="bi bi-search"></i>
            </span>
            <input
                type="search"
                id="{{ $toolbarId }}-search"
                class="form-control"
                data-table-search
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
            >
        </div>
    </div>

    @foreach ($filters as $filter)
        @php
            $name = $filter['name'] ?? 'filter';
            $label = $filter['label'] ?? $name;
            $options = $filter['options'] ?? [];
            $inputId = $toolbarId.'-'.$name;
        @endphp
        <div class="col-12 col-sm-6 col-md-auto" style="min-width: 10rem;">
            <label class="form-label" for="{{ $inputId }}">{{ $label }}</label>
            <select id="{{ $inputId }}" class="form-select" data-table-filter="{{ $name }}">
                <option value="">{{ __('general.all') }}</option>
                @foreach ($options as $value => $optionLabel)
                    <option value="{{ $value }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>
