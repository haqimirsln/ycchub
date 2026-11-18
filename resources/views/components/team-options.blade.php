@props(['selected' => '', 'withNone' => true])
@foreach (config('ycc.teams') as $key => $name)
    <option value="{{ $key }}" @selected($selected === $key)>{{ $name }}</option>
@endforeach
@if ($withNone)
    <option value="none" @selected($selected === 'none')>No team yet</option>
@endif
