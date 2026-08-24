@php
    $cards = $snapshot['cards'];
    $trend = $snapshot['trend'];
    $roles = $snapshot['roles'];
    $modules = $snapshot['modules'];
    $hours = $snapshot['hours'];
    $trendMax = max(1, ...array_column($trend, 'value'));
    $roleTotal = max(1, (int) array_sum(array_column($roles, 'value')));
    $moduleMax = max(1, ...array_column($modules, 'value'));
    $donutStops = [];
    $cursor = 0;
    foreach ($roles as $slice) {
        if ($slice['value'] <= 0) {
            continue;
        }
        $start = $cursor;
        $cursor += ($slice['value'] / $roleTotal) * 100;
        $donutStops[] = $slice['color'].' '.$start.'% '.$cursor.'%';
    }
    $donutCss = $donutStops === []
        ? 'conic-gradient(#dee2e6 0 100%)'
        : 'conic-gradient('.implode(', ', $donutStops).')';
    $planned = (float) $hours['planned'];
    $actual = (float) $hours['actual'];
    $hoursPct = $planned > 0 ? min(100, (int) round(($actual / $planned) * 100)) : 0;
    $tz = (string) config('app.timezone');
    $now = now()->timezone($tz);
@endphp

@extends('layouts.app')

@section('title', __('portal.title'))

@section('content')
    <div class="container-lg py-2 py-md-3">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h1 class="h3 mb-1">{{ __('portal.stats_title') }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ $snapshot['scope'] === 'team' ? __('portal.stats_scope_team') : __('portal.stats_scope_company') }}
                </p>
            </div>
            <div class="ms-sm-auto" data-portal-clock data-timezone="{{ $tz }}" data-locale="{{ str_replace('_', '-', app()->getLocale()) }}">
                <div class="fw-semibold">{{ $now->translatedFormat('l, d F Y') }}</div>
                <div class="h4 mb-0 font-monospace num-ltr" data-portal-clock-time>{{ $now->format('H:i:s') }}</div>
                <div class="small text-body-secondary">
                    {{ __('portal.clock.now') }}
                    ·
                    {{ __('portal.clock.zone', ['zone' => $tz, 'offset' => $now->format('P')]) }}
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <x-stat-card :title="__('portal.cards.staff')" :value="$cards['staff']" icon="bi-people" color="primary" />
            </div>
            <div class="col-6 col-lg-2">
                <x-stat-card :title="__('portal.cards.staff_active')" :value="$cards['staff_active']" icon="bi-person-check" color="success" />
            </div>
            <div class="col-6 col-lg-2">
                <x-stat-card :title="__('portal.cards.present_today')" :value="$cards['present_today']" icon="bi-check2-circle" color="success" />
            </div>
            <div class="col-6 col-lg-2">
                <x-stat-card :title="__('portal.cards.late_today')" :value="$cards['late_today']" icon="bi-clock-history" color="warning" />
            </div>
            <div class="col-6 col-lg-2">
                <x-stat-card :title="__('portal.cards.open_punches')" :value="$cards['open_punches']" icon="bi-record-circle" color="info" />
            </div>
            <div class="col-6 col-lg-2">
                <x-stat-card :title="__('portal.cards.missing_reports')" :value="$cards['missing_reports']" icon="bi-journal-x" color="danger" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h2 class="h6 mb-0">{{ __('portal.charts.attendance_trend') }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="portal-bar-chart" role="img" aria-label="{{ __('portal.charts.attendance_trend') }}">
                            @foreach ($trend as $bar)
                                <div class="portal-bar">
                                    <div class="portal-bar-track" title="{{ $bar['label'] }}: {{ $bar['value'] }}">
                                        <div class="portal-bar-fill" style="height: {{ max(4, (int) round(($bar['value'] / $trendMax) * 100)) }}%;"></div>
                                    </div>
                                    <div class="portal-bar-label text-body-secondary">
                                        <span class="num-ltr d-block">{{ $bar['value'] }}</span>
                                        {{ $bar['label'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h2 class="h6 mb-0">{{ __('portal.charts.staff_roles') }}</h2>
                    </div>
                    <div class="card-body d-flex flex-column flex-sm-row align-items-center gap-3">
                        <div class="portal-donut" style="background: {{ $donutCss }};" role="img" aria-label="{{ __('portal.charts.staff_roles') }}"></div>
                        <ul class="list-unstyled mb-0 small">
                            @foreach ($roles as $slice)
                                <li class="d-flex align-items-center gap-2 mb-1">
                                    <span class="rounded-circle flex-shrink-0" style="width:.7rem;height:.7rem;background:{{ $slice['color'] }};"></span>
                                    <span>{{ $slice['label'] }}</span>
                                    <span class="num-ltr ms-auto">{{ $slice['value'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h2 class="h6 mb-0">{{ __('portal.charts.month_hours') }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ __('portal.charts.actual') }}</span>
                            <span class="num-ltr">{{ number_format($actual, 2) }} / {{ number_format($planned, 2) }}</span>
                        </div>
                        <div class="portal-hours-track mb-2" role="img" aria-label="{{ __('portal.charts.month_hours') }}">
                            <div class="portal-hours-fill" style="width: {{ $hoursPct }}%;"></div>
                        </div>
                        <p class="small text-body-secondary mb-0">{{ __('portal.charts.month_hours_hint') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h2 class="h6 mb-0">{{ __('portal.charts.modules') }}</h2>
                    </div>
                    <div class="card-body">
                        @foreach ($modules as $row)
                            @php $pct = (int) round(($row['value'] / $moduleMax) * 100); @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>{{ $row['label'] }}</span>
                                    <span class="num-ltr">{{ $row['value'] }}</span>
                                </div>
                                <div class="portal-hours-track">
                                    <div class="portal-hours-fill" style="width: {{ $row['value'] === 0 ? 0 : max(4, $pct) }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <h2 class="h5 mb-3">{{ __('portal.modules_title') }}</h2>
        @php
            $moduleCards = $moduleCards ?? [
                ['id' => 'hr', 'route' => 'portal.hr', 'icon' => 'bi-people'],
            ];
            $cardCount = count($moduleCards);
            $moduleCol = match (true) {
                $cardCount >= 4 => 'col-12 col-sm-6 col-lg-3',
                $cardCount === 3 => 'col-12 col-md-4',
                $cardCount === 2 => 'col-12 col-md-6',
                default => 'col-12 col-md-6 col-lg-4',
            };
        @endphp
        <div class="row g-3">
            @foreach ($moduleCards as $card)
                <div class="{{ $moduleCol }}">
                    <a class="card h-100 text-decoration-none text-body shadow-sm"
                       href="{{ route($card['route']) }}">
                        <div class="card-body d-flex flex-column p-4">
                            <i class="bi {{ $card['icon'] }} fs-1 text-primary mb-3" aria-hidden="true"></i>
                            <h3 class="h4">{{ __('portal.'.$card['id']) }}</h3>
                            <span class="mt-auto pt-3 fw-semibold text-primary">{{ __('portal.open') }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection
