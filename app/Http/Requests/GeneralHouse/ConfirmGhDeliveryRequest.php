<?php

namespace App\Http\Requests\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmGhDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GhOrder $order */
        $order = $this->route('order');

        return GhOrderAccess::mayConfirmDelivery($this->user(), $order);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var GhOrder $order */
        $order = $this->route('order');
        $needsMethod = $this->deliveryNeedsPaymentMethod($order);

        return [
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['prohibited'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'payment_method' => [
                Rule::requiredIf($needsMethod),
                'nullable',
                Rule::in([GhPaymentMethod::CASH, GhPaymentMethod::ONLINE]),
            ],
            'cash_collected_amount' => [
                Rule::requiredIf(function () use ($order) {
                    // Recomplete (delivered_at set) never collects again.
                    if ($order->delivered_at !== null) {
                        return false;
                    }

                    return strtoupper(trim((string) $this->input('payment_method', ''))) === GhPaymentMethod::CASH;
                }),
                'nullable',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:999999999.99',
            ],
            'cash_outcome' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cash_collected_amount.required' => __('gh.errors.cash_collected_amount_required'),
            'cash_collected_amount.gt' => __('gh.errors.cash_collected_amount_required'),
        ];
    }

    private function deliveryNeedsPaymentMethod(GhOrder $order): bool
    {
        if (! GhLaunchConfig::driverDeliveryPaymentEnabled()) {
            return false;
        }

        // Recomplete path — no new collection.
        if ($order->delivered_at !== null) {
            return false;
        }

        $order->loadMissing('invoice');
        if ($order->invoice === null || ! $order->invoice->isIssued()) {
            return false;
        }

        $balance = app(GhPaymentBalanceService::class)->forInvoice($order->invoice);

        return GhMoney::cmp($balance['remaining_due'], '0') > 0;
    }
}
