@extends('layouts.app')

@section('title', __('gh.app_versions.title'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('gh.app_versions.title') }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.app_versions.subtitle') }}</p>
        </div>
        <div class="text-end">
            <span class="badge text-bg-secondary">{{ __('gh.app_versions.trusted_channel') }}:
                <span class="num-ltr text-uppercase">{{ $trustedChannel }}</span>
            </span>
        </div>
    </div>

    <div class="alert alert-info small py-2" role="status">
        {{ __('gh.app_versions.channel_trust_note') }}
    </div>

    <section class="card border-0 shadow-sm mb-4" data-gnrl-mobile-app-config="1" aria-labelledby="gnrl-mobile-app-config-title">
        <div class="card-header bg-transparent">
            <h2 id="gnrl-mobile-app-config-title" class="h5 card-title mb-1">{{ __('gh.app_versions.mobile_config.title') }}</h2>
            <p class="text-body-secondary small mb-0">{{ __('gh.app_versions.mobile_config.subtitle') }}</p>
        </div>
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.application_name') }}</dt>
                <dd class="col-sm-8 col-lg-9 hp-value">{{ $mobileAppConfig['application_name'] }}</dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.package_id') }}</dt>
                <dd class="col-sm-8 col-lg-9 hp-value num-ltr">{{ $mobileAppConfig['package_id'] }}</dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.server_url') }}</dt>
                <dd class="col-sm-8 col-lg-9 hp-value num-ltr text-break">{{ $mobileAppConfig['server_url'] }}</dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.platform') }}</dt>
                <dd class="col-sm-8 col-lg-9 hp-value">{{ __('gh.app_versions.platform.'.$mobileAppConfig['platform']) }}</dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.environment') }}</dt>
                <dd class="col-sm-8 col-lg-9">
                    <span class="badge {{ $mobileAppConfig['channel'] === 'live' ? 'text-bg-success' : 'text-bg-secondary' }} num-ltr">
                        {{ $mobileAppConfig['channel_label'] }}
                    </span>
                </dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.version') }}</dt>
                <dd class="col-sm-8 col-lg-9 hp-value num-ltr">
                    @if ($mobileAppConfig['version_name'])
                        {{ $mobileAppConfig['version_name'] }}
                        @if ($mobileAppConfig['version_build'] !== null)
                            <span class="text-body-secondary">({{ __('gh.app_versions.mobile_config.build') }} {{ $mobileAppConfig['version_build'] }})</span>
                        @endif
                    @else
                        {{ __('gh.app_versions.mobile_config.version_unavailable') }}
                    @endif
                </dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.apk_status') }}</dt>
                <dd class="col-sm-8 col-lg-9">
                    <span class="badge {{ $mobileAppConfig['apk_available'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ __('gh.app_versions.mobile_config.apk_status_values.'.$mobileAppConfig['apk_status_key']) }}
                    </span>
                    <span class="badge {{ $mobileAppConfig['publication_status'] === 'PUBLISHED' ? 'text-bg-success' : 'text-bg-secondary' }} ms-1">
                        {{ __('gh.app_versions.status.'.$mobileAppConfig['publication_status']) }}
                    </span>
                </dd>

                <dt class="col-sm-4 col-lg-3">{{ __('gh.app_versions.mobile_config.download_url') }}</dt>
                <dd class="col-sm-8 col-lg-9 hp-value num-ltr text-break">
                    @if ($mobileAppConfig['download_url'])
                        <a href="{{ $mobileAppConfig['download_url'] }}" class="link-primary">{{ $mobileAppConfig['download_url'] }}</a>
                    @else
                        {{ __('gh.app_versions.mobile_config.download_unavailable') }}
                    @endif
                </dd>
            </dl>

            @unless ($mobileAppConfig['host_matches_channel'])
                <div class="alert alert-warning small py-2 mb-0 mt-3" role="status">
                    {{ __('gh.app_versions.mobile_config.host_mismatch', [
                        'app_host' => $mobileAppConfig['app_url_host'],
                        'channel' => $mobileAppConfig['channel_label'],
                    ]) }}
                </div>
            @endunless
        </div>
    </section>

    @foreach ($platforms as $platform)
        <h2 class="h5 mt-4 mb-3">{{ __('gh.app_versions.platform.'.$platform) }}</h2>
        <div class="row g-3 mb-2">
            @foreach (($policiesByPlatform[$platform] ?? collect()) as $policy)
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h3 class="h6 card-title mb-0 text-uppercase num-ltr">{{ $policy->channel }}</h3>
                            <span class="badge {{ $policy->publication_status === 'PUBLISHED' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ __('gh.app_versions.status.'.$policy->publication_status) }}
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="{{ route('admin.app.versions.update', $policy) }}" class="vstack gap-3">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label class="form-label" for="latest_version_name_{{ $policy->id }}">{{ __('gh.app_versions.fields.latest_version_name') }}</label>
                                    <input type="text" class="form-control" id="latest_version_name_{{ $policy->id }}"
                                           name="latest_version_name"
                                           value="{{ old('latest_version_name', $policy->latest_version_name) }}">
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label" for="latest_build_{{ $policy->id }}">{{ __('gh.app_versions.fields.latest_build') }}</label>
                                        <input type="number" min="0" class="form-control num-ltr" id="latest_build_{{ $policy->id }}"
                                               name="latest_build" required
                                               value="{{ old('latest_build', $policy->latest_build) }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="minimum_build_{{ $policy->id }}">{{ __('gh.app_versions.fields.minimum_build') }}</label>
                                        <input type="number" min="0" class="form-control num-ltr" id="minimum_build_{{ $policy->id }}"
                                               name="minimum_build" required
                                               value="{{ old('minimum_build', $policy->minimum_build) }}">
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label" for="update_mode_{{ $policy->id }}">{{ __('gh.app_versions.fields.update_mode') }}</label>
                                    <select class="form-select" id="update_mode_{{ $policy->id }}" name="update_mode" required>
                                        @foreach (['OPTIONAL', 'REQUIRED_BY_MINIMUM'] as $mode)
                                            <option value="{{ $mode }}" @selected(old('update_mode', $policy->update_mode) === $mode)>
                                                {{ __('gh.app_versions.modes.'.$mode) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label" for="update_url_{{ $policy->id }}">{{ __('gh.app_versions.fields.update_url') }}</label>
                                    <input type="url" class="form-control num-ltr" id="update_url_{{ $policy->id }}"
                                           name="update_url"
                                           value="{{ old('update_url', $policy->update_url) }}"
                                           placeholder="{{ $platform === 'ios' ? 'https://apps.apple.com/...' : '' }}">
                                    @if ($platform === 'ios')
                                        <div class="form-text">{{ __('gh.app_versions.ios_url_hint') }}</div>
                                    @endif
                                </div>

                                <div>
                                    <label class="form-label" for="release_notes_ar_{{ $policy->id }}">{{ __('gh.app_versions.fields.release_notes_ar') }}</label>
                                    <textarea class="form-control" id="release_notes_ar_{{ $policy->id }}" name="release_notes_ar" rows="2">{{ old('release_notes_ar', $policy->release_notes_ar) }}</textarea>
                                </div>
                                <div>
                                    <label class="form-label" for="release_notes_en_{{ $policy->id }}">{{ __('gh.app_versions.fields.release_notes_en') }}</label>
                                    <textarea class="form-control" id="release_notes_en_{{ $policy->id }}" name="release_notes_en" rows="2">{{ old('release_notes_en', $policy->release_notes_en) }}</textarea>
                                </div>

                                <div>
                                    <label class="form-label" for="publication_status_{{ $policy->id }}">{{ __('gh.app_versions.fields.publication_status') }}</label>
                                    <select class="form-select" id="publication_status_{{ $policy->id }}" name="publication_status" required>
                                        @foreach (['UNPUBLISHED', 'PUBLISHED'] as $status)
                                            <option value="{{ $status }}" @selected(old('publication_status', $policy->publication_status) === $status)>
                                                {{ __('gh.app_versions.status.'.$status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label" for="reason_{{ $policy->id }}">{{ __('gh.app_versions.fields.reason') }}</label>
                                    <input type="text" class="form-control" id="reason_{{ $policy->id }}" name="reason" maxlength="500">
                                </div>

                                <dl class="row small mb-0">
                                    <dt class="col-5">{{ __('gh.app_versions.fields.package_id') }}</dt>
                                    <dd class="col-7 hp-value num-ltr">{{ $policy->package_id ?: '—' }}</dd>
                                    <dt class="col-5">{{ __('gh.app_versions.fields.updated_at') }}</dt>
                                    <dd class="col-7 hp-value num-ltr">{{ optional($policy->updated_at)->timezone(config('app.timezone'))?->translatedFormat('d M Y H:i') ?: '—' }}</dd>
                                    <dt class="col-5">{{ __('gh.app_versions.fields.updated_by') }}</dt>
                                    <dd class="col-7">{{ $policy->updater?->name_en ?? $policy->updater?->name_ar ?? '—' }}</dd>
                                </dl>

                                <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.app_versions.save') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <h2 class="h5 mt-4 mb-3">{{ __('gh.app_versions.audit_title') }}</h2>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>{{ __('gh.app_versions.audit.when') }}</th>
                    <th>{{ __('gh.app_versions.audit.actor') }}</th>
                    <th>{{ __('gh.app_versions.audit.target') }}</th>
                    <th>{{ __('gh.app_versions.audit.action') }}</th>
                    <th>{{ __('gh.app_versions.audit.reason') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($audits as $audit)
                    <tr>
                        <td class="num-ltr small">{{ $audit->recorded_at?->timezone(config('app.timezone'))?->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">{{ $audit->actor?->name_en ?? $audit->actor?->name_ar ?? '—' }}</td>
                        <td class="small num-ltr text-uppercase">{{ $audit->policy?->platform }}/{{ $audit->policy?->channel }}</td>
                        <td class="small num-ltr">{{ $audit->action }}</td>
                        <td class="small">{{ $audit->reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-body-secondary small">{{ __('gh.app_versions.audit.empty') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
