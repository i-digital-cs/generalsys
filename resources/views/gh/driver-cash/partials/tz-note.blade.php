@php
    use App\Support\GeneralHouse\GhDateTimePresenter;
    $tz = $tz ?? GhDateTimePresenter::timezone();
@endphp
<p class="small text-body-secondary mb-0 gh-uae-tz-notice">
    <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ __('gh.time.list_note', ['tz' => $tz]) }}
    <span class="gh-uae-badge">{{ GhDateTimePresenter::uaeBadge() }}</span>
</p>
