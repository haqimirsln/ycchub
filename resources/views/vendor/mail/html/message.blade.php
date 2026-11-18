@php
    $social = \App\Http\Controllers\PublicController::social();
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Young Conservationist Club, Universiti Malaysia Terengganu

[Instagram]({{ $social['instagram_url'] }}) · [LinkedIn]({{ $social['linkedin_url'] }}) · [YCC Hub]({{ rtrim(config('app.url'), '/') }})

You're getting this email because of your YCC Hub account.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
