@extends('layouts.app')

@section('title', __('employees.create_title'))

@section('content')
    <h1 class="h4 mb-3">{{ __('employees.create_title') }}</h1>

    <form method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.employees._form', ['employee' => null, 'submitLabel' => __('general.create')])
    </form>
@endsection

@push('scripts')
    @vite(['resources/js/pages/employee-form.js'])
@endpush
