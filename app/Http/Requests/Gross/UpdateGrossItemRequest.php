<?php

namespace App\Http\Requests\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrossItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GrossItem $item */
        $item = $this->route('item');

        return $this->user()?->can('update', $item) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'color_id' => [
                'nullable',
                'integer',
                Rule::exists('gross_item_attributes', 'id')->where('kind', GrossItemAttribute::KIND_COLOR),
            ],
            'size_id' => [
                'nullable',
                'integer',
                Rule::exists('gross_item_attributes', 'id')->where('kind', GrossItemAttribute::KIND_SIZE),
            ],
            'model_id' => [
                'nullable',
                'integer',
                Rule::exists('gross_item_attributes', 'id')->where('kind', GrossItemAttribute::KIND_MODEL),
            ],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:gross_categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:gross_categories,id'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in([GrossItem::STATUS_ACTIVE, GrossItem::STATUS_INACTIVE])],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:gross_item_images,id'],
        ];
    }
}
