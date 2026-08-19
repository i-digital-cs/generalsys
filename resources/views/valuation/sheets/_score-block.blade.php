@php
    $editable = $editable ?? true;
    $score = fn (string $k) => $editable
        ? old('input.'.$k, $input[$k] ?? 0)
        : ($input[$k] ?? 0);
    $text = fn (string $k) => $editable
        ? old('input.'.$k, $input[$k] ?? '')
        : (string) ($input[$k] ?? '');
    $blank = fn (string $value) => $value === '' ? '—' : $value;
@endphp
<div class="{{ $col ?? 'col-12' }}">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">{{ __('valuation.sections.'.$key) }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="form-label">{{ __('valuation.fields.score') }}</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if ($editable)
                            <label class="visually-hidden" for="{{ $scoreKey }}">{{ __('valuation.fields.score') }}</label>
                            <input class="form-control" dir="ltr" name="input[{{ $scoreKey }}]" id="{{ $scoreKey }}"
                                   value="{{ $score($scoreKey) }}" min="0" max="100" step="1" data-star-source>
                            <x-stars :score="$score($scoreKey)" data-star-out />
                        @else
                            <span class="num-ltr fw-semibold">{{ $score($scoreKey) }}%</span>
                            <x-stars :score="$score($scoreKey)" />
                        @endif
                    </div>
                </div>
                @isset($notesKey)
                    <div class="col-12 col-md-8">
                        <div class="form-label">{{ __('valuation.fields.notes') }}</div>
                        @if ($editable)
                            <textarea name="input[{{ $notesKey }}]" id="{{ $notesKey }}" class="form-control" rows="2">{{ $text($notesKey) }}</textarea>
                        @else
                            <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text($notesKey)) }}</p>
                        @endif
                    </div>
                @endisset
                @isset($extra)
                    @foreach ($extra as $ek)
                        <div class="col-12">
                            <div class="form-label">{{ __('valuation.fields.'.$ek) }}</div>
                            @if ($editable)
                                <textarea name="input[{{ $ek }}]" id="{{ $ek }}" class="form-control" rows="3">{{ $text($ek) }}</textarea>
                            @else
                                <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text($ek)) }}</p>
                            @endif
                        </div>
                    @endforeach
                @endisset
            </div>
        </div>
    </div>
</div>
