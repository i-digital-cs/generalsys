@php
    $item = $item ?? null;
    $nameSuggestions = $nameSuggestions ?? [];
    $colors = $colors ?? collect();
    $sizes = $sizes ?? collect();
    $models = $models ?? collect();
    $rootCategories = $categories->whereNull('parent_id')->values();
    $subCategories = $categories->whereNotNull('parent_id')->values();
    $selectedSubId = old('subcategory_id', $item?->subcategory_id);
    $selectedCatId = old('category_id', $item?->category_id);
    $selectedSub = $selectedSubId
        ? $subCategories->firstWhere('id', (int) $selectedSubId)
        : null;
    if ($selectedSub && $selectedSub->parent_id) {
        $selectedCatId = $selectedSub->parent_id;
    }
@endphp
<div class="row g-2 mb-3" data-gross-item-form>
    <div class="col-md-4">
        <label class="form-label mb-1" for="code">{{ __('gross.fields.code') }}</label>
        @if ($item)
            <input id="code" class="form-control form-control-sm" value="{{ $item->code }}" readonly>
        @else
            <input id="code" class="form-control form-control-sm" value="{{ __('gross.fields.code_auto') }}" readonly disabled>
        @endif
    </div>
    <div class="col-md-8">
        <label class="form-label mb-1" for="name">{{ __('gross.fields.name') }}</label>
        <input id="name" name="name" list="gross-suggest-name"
               class="form-control form-control-sm @error('name') is-invalid @enderror"
               value="{{ old('name', $item?->name) }}" required autocomplete="off"
               placeholder="{{ __('gross.fields.name_suggest_hint') }}">
        <datalist id="gross-suggest-name">
            @foreach ($nameSuggestions as $value)
                <option value="{{ $value }}"></option>
            @endforeach
        </datalist>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="color_id">{{ __('gross.fields.color') }}</label>
        <select id="color_id" name="color_id" class="form-select form-select-sm @error('color_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach ($colors as $opt)
                <option value="{{ $opt->id }}" @selected((string) old('color_id', $item?->color_id) === (string) $opt->id)>
                    {{ $opt->localizedName() }}
                </option>
            @endforeach
        </select>
        @error('color_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="size_id">{{ __('gross.fields.size') }}</label>
        <select id="size_id" name="size_id" class="form-select form-select-sm @error('size_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach ($sizes as $opt)
                <option value="{{ $opt->id }}" @selected((string) old('size_id', $item?->size_id) === (string) $opt->id)>
                    {{ $opt->localizedName() }}
                </option>
            @endforeach
        </select>
        @error('size_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="model_id">{{ __('gross.fields.model') }}</label>
        <select id="model_id" name="model_id" class="form-select form-select-sm @error('model_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach ($models as $opt)
                <option value="{{ $opt->id }}" @selected((string) old('model_id', $item?->model_id) === (string) $opt->id)>
                    {{ $opt->localizedName() }}
                </option>
            @endforeach
        </select>
        @error('model_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <p class="small text-body-secondary mb-0">
            <a href="{{ route('gross.attributes.index') }}">{{ __('gross.attributes.manage_link') }}</a>
        </p>
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="reorder_level">{{ __('gross.fields.reorder_level') }}</label>
        <input id="reorder_level" name="reorder_level" type="number" min="0" class="form-control form-control-sm"
               value="{{ old('reorder_level', $item?->reorder_level ?? 5) }}" required
               title="{{ __('gross.fields.reorder_level_hint') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="default_sale_price">{{ __('gross.fields.default_sale_price') }}</label>
        <input id="default_sale_price" name="default_sale_price" type="number" step="0.01" min="0" class="form-control form-control-sm"
               value="{{ old('default_sale_price', $item?->default_sale_price) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="status">{{ __('gross.fields.status') }}</label>
        <select id="status" name="status" class="form-select form-select-sm" required>
            @foreach (['ACTIVE', 'INACTIVE'] as $st)
                <option value="{{ $st }}" @selected(old('status', $item?->status ?? 'ACTIVE') === $st)>
                    {{ __('gross.statuses.'.$st) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="category_id">{{ __('gross.fields.category') }}</label>
        <select id="category_id" name="category_id" class="form-select form-select-sm @error('category_id') is-invalid @enderror"
                data-gross-category>
            <option value="">—</option>
            @foreach ($rootCategories as $cat)
                <option value="{{ $cat->id }}" @selected((string) $selectedCatId === (string) $cat->id)>
                    {{ $cat->localizedName() }}
                </option>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label mb-1" for="subcategory_id">{{ __('gross.fields.subcategory') }}</label>
        <select id="subcategory_id" name="subcategory_id" class="form-select form-select-sm @error('subcategory_id') is-invalid @enderror"
                data-gross-subcategory>
            <option value="">—</option>
            @foreach ($subCategories as $cat)
                <option value="{{ $cat->id }}"
                        data-parent="{{ $cat->parent_id }}"
                        @selected((string) $selectedSubId === (string) $cat->id)>
                    {{ $cat->localizedName() }}
                </option>
            @endforeach
        </select>
        @error('subcategory_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <p class="small text-body-secondary mb-2">
            <a href="{{ route('gross.categories.index') }}">{{ __('gross.categories.manage_link') }}</a>
        </p>
    </div>
    <div class="col-12">
        <label class="form-label mb-1" for="description">{{ __('gross.fields.description') }}</label>
        <textarea id="description" name="description" class="form-control form-control-sm" rows="2">{{ old('description', $item?->description) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label mb-1" for="images">{{ __('gross.fields.images') }}</label>
        @if ($item && $item->relationLoaded('images') && $item->images->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach ($item->images as $image)
                    <div class="border rounded p-1 text-center" style="width: 7rem">
                        <img src="{{ route('gross.items.images.show', [$item, $image]) }}"
                             alt="" class="img-fluid rounded mb-1" style="max-height: 5rem; object-fit: cover; width: 100%">
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input" type="checkbox" name="remove_image_ids[]"
                                   value="{{ $image->id }}" id="remove_img_{{ $image->id }}">
                            <label class="form-check-label small" for="remove_img_{{ $image->id }}">{{ __('gross.fields.remove_image') }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <input id="images" name="images[]" type="file" class="form-control form-control-sm @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"
               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
        <div class="form-text">{{ __('gross.fields.images_hint') }}</div>
        @error('images')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
@once
    @push('scripts')
        <script>
            (function () {
                const root = document.querySelector('[data-gross-item-form]');
                if (!root) return;
                const category = root.querySelector('[data-gross-category]');
                const sub = root.querySelector('[data-gross-subcategory]');
                if (!category || !sub) return;

                function filterSubs() {
                    const parentId = category.value || '';
                    Array.from(sub.options).forEach(function (opt) {
                        if (!opt.value) {
                            opt.hidden = false;
                            return;
                        }
                        const match = !parentId || opt.getAttribute('data-parent') === parentId;
                        opt.hidden = !match;
                    });
                    const selected = sub.options[sub.selectedIndex];
                    if (selected && selected.value && selected.hidden) {
                        sub.value = '';
                    }
                }

                sub.addEventListener('change', function () {
                    const opt = sub.options[sub.selectedIndex];
                    const parentId = opt && opt.value ? (opt.getAttribute('data-parent') || '') : '';
                    if (parentId) {
                        category.value = parentId;
                        filterSubs();
                    }
                });

                category.addEventListener('change', filterSubs);
                filterSubs();
            })();
        </script>
    @endpush
@endonce
