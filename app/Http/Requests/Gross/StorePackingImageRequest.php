<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossSalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

class StorePackingImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossSalesInvoice $invoice */
        $invoice = $this->route('invoice');

        return $this->user()?->can('storePackingImage', $invoice) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'packing_image' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp',
            ],
        ];
    }
}
