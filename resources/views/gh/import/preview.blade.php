@extends('layouts.app')

@section('title', __('gh.import.preview.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.import.preview.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.'.$entity.'.title') }} · {{ __('gh.import.preview.expires') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-card :title="__('gh.import.preview.total')" :value="$preview->total" icon="bi-list-ul" /></div>
        <div class="col-6 col-lg-3"><x-stat-card :title="__('gh.import.preview.create')" :value="$preview->create" icon="bi-plus-circle" color="success" /></div>
        <div class="col-6 col-lg-3"><x-stat-card :title="__('gh.import.preview.duplicate')" :value="$preview->duplicate" icon="bi-files" color="warning" /></div>
        <div class="col-6 col-lg-3"><x-stat-card :title="__('gh.import.preview.invalid')" :value="$preview->invalid" icon="bi-exclamation-triangle" color="danger" /></div>
    </div>

    @if ($preview->errors !== [])
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>{{ __('gh.import.preview.errors_title') }}</span>
                <a href="{{ route('gh.import.errors', ['entity' => $entity, 'token' => $token]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('gh.import.preview.download_errors') }}
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hr table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 6rem">{{ __('gh.import.preview.row') }}</th>
                            <th>{{ __('gh.import.preview.message') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview->errors as $error)
                            <tr>
                                <td><span class="hp-value num-ltr">{{ $error['row'] }}</span></td>
                                <td>{{ $error['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($preview->duplicates !== [])
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header">{{ __('gh.import.preview.duplicates_title') }}</div>
            <div class="table-responsive">
                <table class="table table-hr table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 6rem">{{ __('gh.import.preview.row') }}</th>
                            <th>{{ __('gh.import.preview.key') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview->duplicates as $duplicate)
                            <tr>
                                <td><span class="hp-value num-ltr">{{ $duplicate['row'] }}</span></td>
                                <td><span class="hp-value">{{ $duplicate['key'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="small text-body-secondary">
        {{ $preview->hasRowsToApply() ? __('gh.import.preview.skip_note') : __('gh.import.preview.nothing') }}
    </p>

    <div class="d-flex flex-wrap gap-2">
        @if ($preview->hasRowsToApply())
            <form method="post" action="{{ route('gh.import.confirm', ['entity' => $entity, 'token' => $token]) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('gh.import.preview.confirm') }}
                </button>
            </form>
        @endif
        <a href="{{ route('gh.'.$entity.'.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.import.preview.cancel') }}</a>
    </div>
@endsection
