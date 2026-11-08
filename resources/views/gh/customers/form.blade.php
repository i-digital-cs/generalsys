@extends('layouts.app')

@php $isEdit = $customer !== null; $title = $isEdit ? __('gh.customers.edit') : __('gh.customers.create'); @endphp
@section('title', $title)

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0">{{ $title }}</h1>
        <a href="{{ route('gh.customers.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('gh.customers.back') }}</a>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ $isEdit ? route('gh.customers.update', $customer) : route('gh.customers.store') }}" class="row g-3" id="gh-customer-form"
                  @unless($isEdit) data-lookup-url="{{ route('gh.customers.lookup') }}" @endunless>
                @csrf
                @if ($isEdit) @method('PUT') @endif
                <div class="col-md-3">
                    <label class="form-label" for="type">{{ __('gh.fields.type') }}</label>
                    <select id="type" name="type" class="form-select" required>
                        @foreach (\App\Support\GeneralHouse\GhCustomerType::ALL as $t)
                            <option value="{{ $t }}" @selected(old('type', $customer->type ?? 'B2C') === $t)>{{ __('gh.customer_type.'.$t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">{{ __('gh.fields.status') }}</label>
                    <select id="status" name="status" class="form-select" required>
                        @foreach (\App\Support\GeneralHouse\GhCatalogStatus::ALL as $st)
                            <option value="{{ $st }}" @selected(old('status', $customer->status ?? 'ACTIVE') === $st)>{{ __('gh.status.'.$st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label" for="name_ar">{{ __('gh.fields.name_ar') }}</label>
                    <input id="name_ar" name="name_ar" class="form-control" required maxlength="150" value="{{ old('name_ar', $customer->name_ar ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label" for="name_en">{{ __('gh.fields.name_en') }}</label>
                    <input id="name_en" name="name_en" class="form-control" maxlength="150" value="{{ old('name_en', $customer->name_en ?? '') }}"></div>
                <div class="col-md-3">
                    <label class="form-label" for="phone">{{ __('gh.fields.phone') }}</label>
                    <input id="phone" name="phone" class="form-control" maxlength="40" value="{{ old('phone', $customer->phone ?? '') }}" autocomplete="tel">
                    @unless ($isEdit)
                        <div id="gh-phone-dup" class="mt-2 d-none" role="status"></div>
                        <div class="form-check mt-2 d-none" id="gh-allow-dup-wrap">
                            <input class="form-check-input" type="checkbox" value="1" id="allow_duplicate_phone" name="allow_duplicate_phone"
                                   @checked(old('allow_duplicate_phone'))>
                            <label class="form-check-label small" for="allow_duplicate_phone">{{ __('gh.customers.allow_duplicate_phone') }}</label>
                        </div>
                    @endunless
                </div>
                <div class="col-md-3"><label class="form-label" for="email">{{ __('gh.fields.email') }}</label>
                    <input id="email" name="email" type="email" class="form-control" maxlength="190" value="{{ old('email', $customer->email ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label" for="tax_registration_number">{{ __('gh.fields.tax_registration_number') }}</label>
                    <input id="tax_registration_number" name="tax_registration_number" class="form-control" maxlength="80" value="{{ old('tax_registration_number', $customer->tax_registration_number ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label" for="billing_name">{{ __('gh.fields.billing_name') }}</label>
                    <input id="billing_name" name="billing_name" class="form-control" maxlength="190" value="{{ old('billing_name', $customer->billing_name ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label" for="billing_address">{{ __('gh.fields.billing_address') }}</label>
                    <input id="billing_address" name="billing_address" class="form-control" maxlength="500" value="{{ old('billing_address', $customer->billing_address ?? '') }}"></div>
                <div class="col-12"><label class="form-label" for="notes">{{ __('gh.fields.notes') }}</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $customer->notes ?? '') }}</textarea></div>
                <div class="col-12" id="gh-name-dup" class="d-none" role="status"></div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('general.save') }}</button>
                    <a href="{{ route('gh.customers.index') }}" class="btn btn-outline-secondary">{{ __('general.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@unless ($isEdit)
@push('scripts')
<script>
(() => {
  const form = document.getElementById('gh-customer-form');
  const url = form?.dataset.lookupUrl;
  if (!form || !url) return;
  const phoneInput = document.getElementById('phone');
  const nameAr = document.getElementById('name_ar');
  const nameEn = document.getElementById('name_en');
  const phoneBox = document.getElementById('gh-phone-dup');
  const nameBox = document.getElementById('gh-name-dup');
  const allowWrap = document.getElementById('gh-allow-dup-wrap');
  const labels = {
    phoneTitle: @json(__('gh.customers.phone_match_title')),
    nameTitle: @json(__('gh.customers.name_match_title')),
    open: @json(__('gh.customers.open_existing')),
  };
  let timer = null;

  const renderList = (title, rows, warningClass) => {
    if (!rows.length) return '';
    const items = rows.map((r) => {
      const type = r.type || '';
      return `<li class="mb-1"><a href="${r.url}" class="link-primary">${r.name}</a>
        <span class="text-body-secondary small num-ltr">${r.phone || ''}</span>
        <span class="badge text-bg-secondary">${type}</span>
        <a href="${r.url}" class="btn btn-sm btn-outline-primary ms-1">${labels.open}</a></li>`;
    }).join('');
    return `<div class="alert ${warningClass} py-2 small mb-0"><div class="fw-semibold mb-1">${title}</div><ul class="mb-0 ps-3">${items}</ul></div>`;
  };

  const lookup = () => {
    const params = new URLSearchParams({
      phone: phoneInput.value || '',
      name_ar: nameAr.value || '',
      name_en: nameEn.value || '',
    });
    fetch(`${url}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => {
        const phones = data.phone_matches || [];
        const names = data.name_matches || [];
        if (phones.length) {
          phoneBox.classList.remove('d-none');
          phoneBox.innerHTML = renderList(labels.phoneTitle, phones, 'alert-warning');
          allowWrap?.classList.remove('d-none');
        } else {
          phoneBox.classList.add('d-none');
          phoneBox.innerHTML = '';
          allowWrap?.classList.add('d-none');
          const cb = document.getElementById('allow_duplicate_phone');
          if (cb) cb.checked = false;
        }
        // Name suggestions exclude exact phone matches already listed when possible.
        const phoneIds = new Set(phones.map((p) => p.id));
        const nameOnly = names.filter((n) => !phoneIds.has(n.id));
        if (nameOnly.length) {
          nameBox.classList.remove('d-none');
          nameBox.innerHTML = renderList(labels.nameTitle, nameOnly, 'alert-secondary');
        } else {
          nameBox.classList.add('d-none');
          nameBox.innerHTML = '';
        }
      })
      .catch(() => {});
  };

  const schedule = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(lookup, 350);
  };
  phoneInput?.addEventListener('input', schedule);
  nameAr?.addEventListener('input', schedule);
  nameEn?.addEventListener('input', schedule);
  if ((phoneInput?.value || '').trim() !== '') schedule();
})();
</script>
@endpush
@endunless
