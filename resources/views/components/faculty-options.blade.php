@props(['selected' => '', 'withNone' => false])
{{-- Faculty <option>s for filters: the faculties members have actually typed, shortest form shown. --}}
@php
    $faculties = \App\Models\User::query()->tracked()->whereNotNull('faculty')->where('faculty', '!=', '')
        ->distinct()->orderBy('faculty')->pluck('faculty');
@endphp
@foreach ($faculties as $name)
    @php $label = config('ycc.faculties.'.$name) ?? $name; @endphp
    <option value="{{ $name }}" title="{{ $label }}" @selected($selected === $name)>{{ \Illuminate\Support\Str::of($label)->after('Faculty of ')->limit(34) }}</option>
@endforeach
@if ($withNone)
    <option value="none" @selected($selected === 'none')>Not set</option>
@endif
