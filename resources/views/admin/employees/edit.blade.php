@extends('layouts.app')

@section('title', __('employees.edit_title'))

@section('content')
    <h1 class="h4 mb-3">{{ __('employees.edit_title') }} — {{ app()->getLocale() === 'ar' ? $employee->name_ar : $employee->name_en }}</h1>

    <form method="POST" action="{{ route('admin.employees.update', $employee) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.employees._form', ['submitLabel' => __('general.save')])
    </form>

    @include('admin.employees._documents')
@endsection

@push('scripts')
    @vite(['resources/js/pages/employee-form.js'])
@endpush
