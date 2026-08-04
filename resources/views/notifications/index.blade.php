@extends('layouts.app')

@section('title', __('notifications.title'))

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ __('notifications.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('notifications.subtitle') }}</p>
        </div>
        @if (auth()->user()->unreadNotifications->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    {{ __('notifications.mark_all_read') }}
                </button>
            </form>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if ($notifications->isEmpty())
                <x-empty-state
                    :title="__('notifications.empty_title')"
                    :text="__('notifications.empty_text')"
                    icon="bi-bell"
                />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($notifications as $item)
                        <li class="list-group-item px-0">
                            <a href="{{ route('notifications.open', $item->id) }}"
                               class="d-flex justify-content-between align-items-start gap-3 text-decoration-none text-body">
                                <div>
                                    <div class="fw-semibold">
                                        {{ \App\Support\NotificationMessage::title($item->data) }}
                                        @if ($item->read_at === null)
                                            <span class="badge text-bg-primary">{{ __('notifications.unread') }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-body-secondary">
                                        {{ \App\Support\NotificationMessage::body($item->data) }}
                                    </div>
                                </div>
                                <span class="small text-body-secondary text-nowrap">
                                    {{ $item->created_at?->diffForHumans() }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
