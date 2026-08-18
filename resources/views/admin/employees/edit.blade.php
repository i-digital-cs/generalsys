@extends('layouts.app')

@section('title', __('employees.edit_title'))

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('employees.edit_title') }} — {{ app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en }}</h1>
        @if ($canImpersonateThis ?? false)
            <x-impersonate-button :user="$employee" size="sm" />
        @endif
    </div>

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.employees._form', ['submitLabel' => __('general.save')])
    </form>

    @include('admin.employees._rest_days')
    @include('admin.employees._documents')
@endsection

@push('scripts')
    @vite(['resources/js/pages/employee-form.js'])
@endpush