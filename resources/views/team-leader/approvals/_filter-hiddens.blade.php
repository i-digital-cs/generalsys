@foreach (($filterQuery ?? ['filter' => $filter ?? 'pending']) as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endforeach
