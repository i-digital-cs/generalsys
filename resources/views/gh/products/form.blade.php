@extends('layouts.app')

@php
    $isEdit = $product !== null;
    $title = $isEdit ? __('gh.products.edit') : __('gh.products.create');
@endphp

@section('title', $title)

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <p class="text-body-secondary small mb-0">{{ __('gh.products.subtitle') }}</p>
        </div>
        <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.products.back') }}</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post"
                  action="{{ $isEdit ? route('gh.products.update', $product) : route('gh.products.store') }}"
                  enctype="multipart/form-data" class="row g-3">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="col-md-4">
                    <label class="form-label" for="category_id">{{ __('gh.fields.category') }}</label>
                    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}"
                                @selected((string) old('category_id', $product->category_id ?? '') === (string) $cat->id)>
                                {{ $cat->localizedName() }}
                                @if (! $cat->isActive()) ({{ __('gh.status.INACTIVE') }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="code">{{ __('gh.fields.code') }}</label>
                    <input id="code" name="code" class="form-control @error('code') is-invalid @enderror"
                           value="{{ old('code', $product->code ?? '') }}" required maxlength="40" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">{{ __('gh.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" required>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(old('status', $product->status ?? 'ACTIVE') === $st)>
                                {{ __('gh.status.'.$st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name_ar">{{ __('gh.fields.name_ar') }}</label>
                    <input id="name_ar" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror"
                           value="{{ old('name_ar', $product->name_ar ?? '') }}" required maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name_en">{{ __('gh.fields.name_en') }}</label>
                    <input id="name_en" name="name_en" class="form-control @error('name_en') is-invalid @enderror"
                           value="{{ old('name_en', $product->name_en ?? '') }}" required maxlength="150" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sort_order">{{ __('gh.fields.sort_order') }}</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" class="form-control"
                           value="{{ old('sort_order', $product->sort_order ?? 0) }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="image">{{ __('gh.fields.image') }}</label>
                    <input id="image" name="image" type="file" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    @if ($isEdit && $product->image_path)
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image"
                                   @checked(old('remove_image'))>
                            <label class="form-check-label" for="remove_image">{{ __('gh.fields.remove_image') }}</label>
                        </div>
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="img-thumbnail mt-2" style="max-height: 96px;">
                        @endif
                    @endif
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('gh.products.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
