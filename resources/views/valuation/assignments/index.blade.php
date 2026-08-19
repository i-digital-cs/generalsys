@extends('layouts.app')

@section('title', __('valuation.assign'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('valuation.assign') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('valuation.assign_hint') }}</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('valuation.assign') }}</div>
        <div class="card-body">
            @if ($users->isEmpty())
                <x-empty-state :title="__('valuation.assign_empty')" :text="__('valuation.assign_empty_text')" icon="bi-people" />
            @else
                <div data-table-filter-root>
                    <x-table-toolbar
                        id="valuation-assign-list"
                        :search-placeholder="__('valuation.assign_search_placeholder')"
                        :filters="[[
                            'name' => 'assignment',
                            'label' => __('valuation.assignment'),
                            'options' => [
                                'none' => __('valuation.assignment_filters.none'),
                                'service' => __('valuation.assignment_filters.service'),
                                'product' => __('valuation.assignment_filters.product'),
                                'both' => __('valuation.assignment_filters.both'),
                            ],
                        ]]"
                    />
                    <div class="table-responsive">
                        <table class="table table-hr table-sm table-hover align-middle mb-0" data-table-filterable>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('employees.name') }}</th>
                                    <th scope="col">{{ __('portal.service') }}</th>
                                    <th scope="col">{{ __('portal.product') }}</th>
                                    <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $row)
                                    @php
                                        $name = app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en;
                                        $mods = $row->valuationAssignments->pluck('module')->all();
                                        $hasService = in_array('service', $mods, true);
                                        $hasProduct = in_array('product', $mods, true);
                                        $assignment = match (true) {
                                            $hasService && $hasProduct => 'both',
                                            $hasService => 'service',
                                            $hasProduct => 'product',
                                            default => 'none',
                                        };
                                        $formId = 'assign-'.$row->id;
                                    @endphp
                                    <tr
                                        data-filter-text="{{ $name }} {{ $row->name_ar }} {{ $row->name_en }}"
                                        data-filter-assignment="{{ $assignment }}"
                                    >
                                        <td class="fw-semibold">{{ $name }}</td>
                                        <td>
                                            <form id="{{ $formId }}" method="POST" action="{{ route('valuation.assignments.update', $row) }}" class="d-none">
                                                @csrf
                                                @method('PUT')
                                            </form>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" form="{{ $formId }}"
                                                       name="modules[]" value="service" id="{{ $formId }}-service"
                                                       @checked($hasService)>
                                                <label class="form-check-label" for="{{ $formId }}-service">{{ __('portal.service') }}</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" form="{{ $formId }}"
                                                       name="modules[]" value="product" id="{{ $formId }}-product"
                                                       @checked($hasProduct)>
                                                <label class="form-check-label" for="{{ $formId }}-product">{{ __('portal.product') }}</label>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary" type="submit" form="{{ $formId }}">
                                                {{ __('valuation.save') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr data-table-empty-row class="d-none">
                                    <td colspan="4" class="text-body-secondary text-center py-3">
                                        {{ __('general.no_filter_results') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
