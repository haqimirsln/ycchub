<x-mail::message>
{!! $body !!}

@if ($program)
<x-mail::panel>
**{{ $program->title }}**<br>
{{ $program->starts_at->format('l, j M Y, g:i A') }}<br>
{{ $program->whereLabel() }}
</x-mail::panel>
@endif

@if ($buttonLabel && $buttonUrl)
<x-mail::button :url="$buttonUrl">
{{ $buttonLabel }}
</x-mail::button>
@elseif ($program)
<x-mail::button :url="route('programs.show', $program)">
View and register
</x-mail::button>
@endif

The YCC MT

<x-slot:subcopy>
You're getting this because you're a YCC member. Don't want club news by email? [Turn it off]({{ $unsubscribeUrl }}). You'll still get account emails like password resets.
</x-slot:subcopy>
</x-mail::message>
