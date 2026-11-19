@php
    use App\Support\GeneralHouse\GhOrderStatus;
    use App\Support\GeneralHouse\GhAssignmentResponsibility;
@endphp

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-transparent"><h2 class="h6 card-title mb-0">{{ __('gh.orders.logistics') }}</h2></div>
    <div class="card-body">
        <div class="row g-3">
            @if ($canConfirmPickup && $order->status === GhOrderStatus::PICKUP)
                <div class="col-12">
                    <h3 class="h6">{{ __('gh.orders.confirm_package_pickup') }}</h3>
                    <p class="small text-body-secondary">{{ __('gh.orders.confirm_package_pickup_hint') }}</p>
                    <form method="post" action="{{ route('gh.orders.confirm-pickup', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_occurred_at">{{ __('gh.orders.actual_pickup_at') }}</label>
                            <input id="pickup_occurred_at" type="datetime-local" name="occurred_at" class="form-control form-control-sm">
                            <x-gh.uae-tz-notice compact />
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="pickup_notes">{{ __('gh.orders.pickup_notes') }}</label>
                            <input id="pickup_notes" name="notes" class="form-control form-control-sm" value="{{ old('notes') }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('gh.orders.confirm_package_pickup') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canConfirmPickup && $order->status === GhOrderStatus::PICKUP)
                <div class="col-md-6">
                    <h3 class="h6">{{ __('gh.orders.failed_pickup') }}</h3>
                    <form method="post" action="{{ route('gh.orders.failed-pickup', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="fail_pickup_notes">{{ __('gh.fields.notes') }}</label>
                            <textarea id="fail_pickup_notes" name="notes" rows="2" class="form-control form-control-sm"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-warning btn-sm">{{ __('gh.orders.record_failed_pickup') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canConfirmDelivery)
                <div class="col-md-6">
                    <h3 class="h6">{{ __('gh.orders.confirm_delivery') }}</h3>
                    <form method="post" action="{{ route('gh.orders.confirm-delivery', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="delivery_occurred_at">{{ __('gh.orders.actual_delivery_at') }}</label>
                            <input id="delivery_occurred_at" type="datetime-local" name="occurred_at" class="form-control form-control-sm">
                            <x-gh.uae-tz-notice compact />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="delivery_notes">{{ __('gh.orders.delivery_notes') }}</label>
                            <textarea id="delivery_notes" name="notes" rows="2" class="form-control form-control-sm"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.orders.confirm_delivery') }}</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6">
                    <h3 class="h6">{{ __('gh.orders.failed_delivery') }}</h3>
                    <form method="post" action="{{ route('gh.orders.failed-delivery', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="fail_delivery_notes">{{ __('gh.fields.notes') }}</label>
                            <textarea id="fail_delivery_notes" name="notes" rows="2" class="form-control form-control-sm"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-warning btn-sm">{{ __('gh.orders.record_failed_delivery') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($order->status === GhOrderStatus::WORKING)
                <div class="col-12">
                    <div class="alert alert-secondary py-2 small mb-2">
                        @if ($order->scheduled_delivery_at)
                            {{ __('gh.orders.delivery_awaiting_schedule') }}
                        @else
                            {{ __('gh.orders.delivery_not_scheduled_working_hint') }}
                        @endif
                    </div>
                    @if ($canManageLogistics)
                        <form method="post" action="{{ route('gh.orders.mark-delivery-ready', $order) }}" class="d-inline"
                              onsubmit="return confirm(@json(__('gh.orders.confirm_mark_delivery_ready')))">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('gh.orders.mark_delivery_ready') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            @php
                $manualDestinations = $manualStatusDestinations ?? [];
                $manualReasonFlags = $manualStatusReasonFlags ?? [];
                $statusRank = [
                    GhOrderStatus::CANCELLED => 0,
                    GhOrderStatus::LATER => 1,
                    GhOrderStatus::PICKUP => 2,
                    GhOrderStatus::WORKING => 3,
                    GhOrderStatus::DELIVERY => 4,
                    GhOrderStatus::COMPLETED => 5,
                ];
                $currentRank = $statusRank[$order->status] ?? 0;
                $showAutoPromoteHold = $canManageLogistics && ! empty($autoPromoteHeld);
            @endphp
            @if ($showAutoPromoteHold)
                <div class="col-12">
                    <div class="alert alert-warning py-2 px-3 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong class="d-block">{{ __('gh.orders.auto_promote_held_title') }}</strong>
                            <span class="small">{{ __('gh.orders.auto_promote_held_hint') }}</span>
                            @if ($order->auto_promote_held_reason)
                                <span class="small d-block text-body-secondary">{{ $order->auto_promote_held_reason }}</span>
                            @endif
                        </div>
                        <form method="post" action="{{ route('gh.orders.resume-auto-promote', $order) }}"
                              onsubmit="return confirm(@json(__('gh.orders.auto_promote_resume_confirm')))">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('gh.orders.auto_promote_resume') }}</button>
                        </form>
                    </div>
                </div>
            @endif
            @if ($canManageLogistics && $manualDestinations !== [])
                <div class="col-12">
                    <h3 class="h6">{{ __('gh.orders.manual_status_title') }}</h3>
                    <p class="small text-body-secondary mb-2">{{ __('gh.orders.manual_status_hint') }}</p>
                    <form method="post" action="{{ route('gh.orders.manual-status', $order) }}" class="row g-2" id="gh-manual-status-form"
                          data-current-status="{{ $order->status }}"
                          data-current-label="{{ __('gh.order_status.'.$order->status) }}"
                          data-confirm-forward="{{ __('gh.orders.manual_status_confirm_forward') }}"
                          data-confirm-backward="{{ __('gh.orders.manual_status_confirm_backward') }}"
                          data-reason-required="{{ __('gh.errors.manual_status_reason_required') }}">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label" for="manual_to_status">{{ __('gh.orders.manual_status_to') }}</label>
                            <select id="manual_to_status" name="to_status" class="form-select form-select-sm" required>
                                <option value="">{{ __('gh.orders.manual_status_select') }}</option>
                                @foreach ($manualDestinations as $dest)
                                    @php
                                        $destRank = $statusRank[$dest] ?? 0;
                                        $needsReason = (bool) ($manualReasonFlags[$dest] ?? false);
                                        $isBackward = $destRank < $currentRank;
                                    @endphp
                                    <option value="{{ $dest }}"
                                            data-direction="{{ $isBackward ? 'backward' : 'forward' }}"
                                            data-reason-required="{{ $needsReason ? '1' : '0' }}"
                                            data-label="{{ __('gh.order_status.'.$dest) }}"
                                            @selected(old('to_status') === $dest)>
                                        {{ __('gh.order_status.'.$dest) }}
                                        ({{ $isBackward ? __('gh.orders.manual_status_backward') : __('gh.orders.manual_status_forward') }})
                                    </option>
                                @endforeach
                            </select>
                            @error('to_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="manual_status_reason">{{ __('gh.orders.manual_status_reason') }}</label>
                            <input id="manual_status_reason" name="reason" class="form-control form-control-sm"
                                   maxlength="500" value="{{ old('reason') }}"
                                   placeholder="{{ __('gh.orders.manual_status_reason_placeholder') }}">
                            @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-warning btn-sm w-100">{{ __('gh.orders.manual_status_submit') }}</button>
                        </div>
                    </form>
                    <script>
                        (function () {
                            const form = document.getElementById('gh-manual-status-form');
                            if (!form) return;
                            form.addEventListener('submit', function (e) {
                                const select = form.querySelector('#manual_to_status');
                                const reason = form.querySelector('#manual_status_reason');
                                const opt = select && select.options[select.selectedIndex];
                                if (!opt || !opt.value) return;
                                const direction = opt.getAttribute('data-direction') || 'forward';
                                const needsReason = opt.getAttribute('data-reason-required') === '1';
                                const toLabel = opt.getAttribute('data-label') || opt.value;
                                const fromLabel = form.getAttribute('data-current-label') || '';
                                if (needsReason && reason && !String(reason.value || '').trim()) {
                                    e.preventDefault();
                                    alert(form.getAttribute('data-reason-required') || '');
                                    reason.focus();
                                    return;
                                }
                                const tpl = direction === 'backward'
                                    ? (form.getAttribute('data-confirm-backward') || '')
                                    : (form.getAttribute('data-confirm-forward') || '');
                                const msg = tpl.replace(':from', fromLabel).replace(':to', toLabel);
                                if (!window.confirm(msg)) {
                                    e.preventDefault();
                                }
                            });
                        })();
                    </script>
                </div>
            @endif

            @if ($canManageLogistics && $order->isEditableOperationally())
                <div class="col-md-6">
                    <h3 class="h6">{{ __('gh.orders.reschedule') }}</h3>
                    <form method="post" action="{{ route('gh.orders.reschedule', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-md-8">
                            <label class="form-label" for="rs_scheduled">{{ __('gh.orders.scheduled_pickup') }}</label>
                            <input id="rs_scheduled" type="datetime-local" name="scheduled_pickup_at" class="form-control form-control-sm" required
                                   value="{{ old('scheduled_pickup_at', $order->pickup_starts_at?->timezone($displayTz)->format('Y-m-d\\TH:i')) }}">
                            <x-gh.uae-tz-notice compact />
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ __('gh.orders.reschedule') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canManageLogistics && $order->status !== GhOrderStatus::CANCELLED && $order->status !== GhOrderStatus::COMPLETED && $order->invoice_issued_at === null)
                <div class="col-md-6">
                    <h3 class="h6">{{ __('gh.orders.cancel') }}</h3>
                    <form method="post" action="{{ route('gh.orders.cancel', $order) }}" class="row g-2" onsubmit="return confirm(@json(__('gh.orders.confirm_cancel')))">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="cancel_reason">{{ __('gh.orders.cancel_reason') }}</label>
                            <input id="cancel_reason" name="cancel_reason" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('gh.orders.cancel') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canManageLogistics && ! in_array($order->status, [GhOrderStatus::COMPLETED, GhOrderStatus::CANCELLED], true))
                <div class="col-12">
                    <h3 class="h6">{{ __('gh.orders.reassign_drivers') }}</h3>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <form method="post" action="{{ route('gh.orders.reassign-driver', $order) }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="responsibility" value="{{ GhAssignmentResponsibility::PICKUP }}">
                                <div class="col-8">
                                    <label class="form-label" for="re_pickup">{{ __('gh.orders.pickup_driver') }}</label>
                                    <select id="re_pickup" name="driver_user_id" class="form-select form-select-sm" required>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}" @selected((int) $order->pickup_driver_id === (int) $driver->id)>{{ $driver->localizedName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">{{ __('gh.orders.reassign') }}</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post" action="{{ route('gh.orders.reassign-driver', $order) }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="responsibility" value="{{ GhAssignmentResponsibility::DELIVERY }}">
                                <div class="col-8">
                                    <label class="form-label" for="re_delivery">{{ __('gh.orders.delivery_driver') }}</label>
                                    <select id="re_delivery" name="driver_user_id" class="form-select form-select-sm">
                                        <option value="">{{ __('gh.orders.none') }}</option>
                                        @foreach ($drivers as $driver)
                                            <option value="{{ $driver->id }}" @selected((int) $order->delivery_driver_id === (int) $driver->id)>{{ $driver->localizedName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">{{ __('gh.orders.reassign') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if ($order->attempts->isNotEmpty())
                <div class="col-12">
                    <h3 class="h6">{{ __('gh.orders.attempts') }}</h3>
                    <ul class="list-unstyled small mb-0">
                        @foreach ($order->attempts as $attempt)
                            <li class="border-bottom py-1">
                                <span class="badge text-bg-warning">{{ $attempt->attempt_type }} / {{ $attempt->outcome }}</span>
                                <span class="num-ltr"><x-gh.dubai-time :at="$attempt->occurred_at" :badge="true" /></span>
                                @if ($attempt->actor) · {{ $attempt->actor->localizedName() }} @endif
                                @if ($attempt->notes) — {{ $attempt->notes }} @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
