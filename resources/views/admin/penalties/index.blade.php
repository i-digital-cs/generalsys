@extends('layouts.app')

@section('title', __('penalties.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('penalties.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('penalties.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.config.penalties.create') }}" class="btn btn-primary">
            {{ __('penalties.create') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('penalties.list') }}</div>
        <div class="card-body">
            @if ($rules->isEmpty())
                <x-empty-state
                    :title="__('penalties.empty_title')"
                    :text="__('penalties.empty_text')"
                    icon="bi-slash-circle"
                />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('penalties.fields.name') }}</th>
                                <th scope="col">{{ __('penalties.fields.type') }}</th>
                                <th scope="col">{{ __('penalties.fields.currency') }}</th>
                                <th scope="col">{{ __('penalties.fields.amount_per_unit') }}</th>
                                <th scope="col">{{ __('penalties.fields.unit') }}</th>
                                <th scope="col">{{ __('penalties.fields.double_penalty_multiplier') }}</th>
                                <th scope="col">{{ __('penalties.fields.status') }}</th>
                                <th scope="col" class="text-end">{{ __('general.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td class="fw-semibold">{{ $rule->name }}</td>
                                    <td>{{ __('penalties.types.'.$rule->type) }}</td>
                                    <td>{{ __('employees.currencies.'.$rule->currency) }}</td>
                                    <td dir="ltr">
                                        <x-money :amount="$rule->amount_per_unit" :currency="$rule->currency" />
                                    </td>
                                    <td>{{ __('penalties.units.'.$rule->unit) }}</td>
                                    <td dir="ltr">×{{ number_format((float) $rule->double_penalty_multiplier, 2) }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'text-bg-success' => $rule->is_active,
                                            'text-bg-secondary' => ! $rule->is_active,
                                        ])>
                                            {{ $rule->is_active ? __('penalties.active') : __('penalties.inactive') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.config.penalties.edit', $rule) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ __('general.edit') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
