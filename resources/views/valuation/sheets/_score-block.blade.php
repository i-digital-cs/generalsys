@php
    use App\Services\Valuation\ValuationCopy;

    $module = $module ?? 'service';
    $editable = $editable ?? true;
    $line = fn (string $key, array $replace = []) => ValuationCopy::line($module, $key, $replace);
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
        <div class="card-header bg-transparent fw-semibold">{{ $line('sections.'.$key) }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="form-label">{{ $line('fields.score') }}</div>
                    @if ($editable)
                        <label class="visually-hidden" for="{{ $scoreKey }}">{{ $line('fields.score') }}</label>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <input class="form-control @error('input.'.$scoreKey) is-invalid @enderror" dir="ltr"
                                   name="input[{{ $scoreKey }}]" id="{{ $scoreKey }}"
                                   value="{{ $score($scoreKey) }}" min="0" max="100" step="1"
                                   inputmode="decimal" autocomplete="off" data-star-source
                                   style="min-width: 5.5rem; max-width: 8rem;">
                            <x-stars :score="$score($scoreKey)" data-star-out class="flex-shrink-0" />
                        </div>
                        @error('input.'.$scoreKey)
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    @else
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="num-ltr fw-semibold">{{ $score($scoreKey) }}%</span>
                            <x-stars :score="$score($scoreKey)" />
                        </div>
                    @endif
                </div>
                @isset($notesKey)
                    <div class="col-12 col-md-8">
                        <div class="form-label">{{ $line('fields.notes') }}</div>
                        @if ($editable)
                            <textarea name="input[{{ $notesKey }}]" id="{{ $notesKey }}"
                                      class="form-control @error('input.'.$notesKey) is-invalid @enderror" rows="2">{{ $text($notesKey) }}</textarea>
                            @error('input.'.$notesKey)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @else
                            <p class="mb-0 text-break" style="white-space: pre-wrap">{{ $blank($text($notesKey)) }}</p>
                        @endif
                    </div>
                @endisset
                @isset($extra)
                    @foreach ($extra as $ek)
                        <div class="col-12">
                            <div class="form-label">{{ $line('fields.'.$ek) }}</div>
                            @if ($editable)
                                <textarea name="input[{{ $ek }}]" id="{{ $ek }}"
                                          class="form-control @error('input.'.$ek) is-invalid @enderror" rows="3">{{ $text($ek) }}</textarea>
                                @error('input.'.$ek)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
