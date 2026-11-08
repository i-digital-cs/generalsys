@extends('layouts.app')

@section('title', __('gh.app.download_title'))

@section('content')
<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width: 3.25rem; height: 3.25rem;" aria-hidden="true">
                            <i class="bi bi-phone fs-3"></i>
                        </div>
                        <div>
                            <h1 class="h4 mb-1">{{ $app['app_label'] }}</h1>
                            <p class="text-body-secondary small mb-0">{{ __('gh.app.subtitle') }}</p>
                        </div>
                    </div>

                    @if ($app['is_test'])
                        <div class="alert alert-warning py-2 small mb-3" role="status">
                            {{ __('gh.app.test_banner') }}
                        </div>
                    @endif

                    <dl class="row small mb-4">
                        <dt class="col-5 col-sm-4">{{ __('gh.app.version') }}</dt>
                        <dd class="col-7 col-sm-8 hp-value num-ltr">{{ $app['version_name'] }}</dd>

                        <dt class="col-5 col-sm-4">{{ __('gh.app.file_size') }}</dt>
                        <dd class="col-7 col-sm-8 hp-value num-ltr">{{ $app['size_label'] }}</dd>

                        <dt class="col-5 col-sm-4">{{ __('gh.app.updated') }}</dt>
                        <dd class="col-7 col-sm-8 hp-value num-ltr">
                            @if (! empty($app['built_at']))
                                {{ \Illuminate\Support\Carbon::parse($app['built_at'])->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}
                            @else
                                —
                            @endif
                        </dd>

                        <dt class="col-5 col-sm-4">{{ __('gh.app.package_id') }}</dt>
                        <dd class="col-7 col-sm-8 small text-break num-ltr">{{ $app['package_id'] }}</dd>
                    </dl>

                    <a href="{{ route('app.download.file') }}"
                       class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-download me-2" aria-hidden="true"></i>{{ __('gh.app.download_button') }}
                    </a>

                    <p class="text-body-secondary small mb-0">{{ __('gh.app.android_only_note') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
