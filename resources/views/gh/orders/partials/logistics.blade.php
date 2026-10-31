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
                    <h3 class="h6">{{ __('gh.orders.confirm_pickup') }}</h3>
                    <p class="small text-body-secondary">{{ __('gh.orders.confirm_pickup_hint') }}</p>
                    <form method="post" action="{{ route('gh.orders.confirm-pickup', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_occurred_at">{{ __('gh.orders.actual_pickup_at') }}</label>
                            <input id="pickup_occurred_at" type="datetime-local" name="occurred_at" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="pickup_notes">{{ __('gh.orders.pickup_notes') }}</label>
                            <input id="pickup_notes" name="notes" class="form-control form-control-sm" value="{{ old('notes') }}">
                        </div>
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-hr table-sm align-middle">
                                    <thead>
                                    <tr>
                                        <th>{{ __('gh.fields.product') }}</th>
                                        <th>{{ __('gh.orders.requested_qty') }}</th>
                                        <th>{{ __('gh.orders.received_qty') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($order->lines as $i => $line)
                                        <tr>
                                            <td>
                                                {{ app()->getLocale() === 'ar' ? $line->product_name_ar : $line->product_name_en }}
                                                /
                                                {{ app()->getLocale() === 'ar' ? $line->treatment_name_ar : $line->treatment_name_en }}
                                                <input type="hidden" name="received[{{ $i }}][order_line_id]" value="{{ $line->id }}">
                                                <input type="hidden" name="received[{{ $i }}][offering_id]" value="{{ $line->offering_id }}">
                                            </td>
                                            <td class="hp-value num-ltr">{{ $line->quantity }}</td>
                                            <td style="min-width:6rem">
                                                <input type="number" min="0" max="9999" class="form-control form-control-sm"
                                                       name="received[{{ $i }}][quantity]" value="{{ old('received.'.$i.'.quantity', $line->quantity) }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @error('received')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('gh.orders.confirm_pickup') }}</button>
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
                    <div class="alert alert-secondary py-2 small mb-0">{{ __('gh.orders.delivery_awaiting_invoice') }}</div>
                </div>
            @endif

            @if ($canManageLogistics && $order->isEditableOperationally())
                <div class="col-md-6">
                    <h3 class="h6">{{ __('gh.orders.reschedule') }}</h3>
                    <form method="post" action="{{ route('gh.orders.reschedule', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-4">
                            <label class="form-label" for="rs_date">{{ __('gh.orders.pickup_date') }}</label>
                            <input id="rs_date" type="date" name="pickup_date" class="form-control form-control-sm"
                                   value="{{ old('pickup_date', $order->pickup_starts_at?->timezone($displayTz)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="rs_start">{{ __('gh.orders.window_start') }}</label>
                            <input id="rs_start" type="time" name="pickup_window_start" class="form-control form-control-sm"
                                   value="{{ old('pickup_window_start', $order->pickup_starts_at?->timezone($displayTz)->format('H:i')) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="rs_end">{{ __('gh.orders.window_end') }}</label>
                            <input id="rs_end" type="time" name="pickup_window_end" class="form-control form-control-sm"
                                   value="{{ old('pickup_window_end', $order->pickup_ends_at?->timezone($displayTz)->format('H:i')) }}">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('gh.orders.reschedule') }}</button>
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
                                <span class="num-ltr">{{ $attempt->occurred_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') }}</span>
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
