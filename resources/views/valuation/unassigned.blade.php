@extends('layouts.app')

@section('title', __('portal.'.$module))

@section('content')
    <h1 class="h3 mb-3">{{ __('portal.'.$module) }}</h1>
    <x-empty-state
        :title="__('valuation.errors.not_assigned')"
        :text="__('valuation.empty_unassigned')"
        icon="bi-lock" />
@endsection
