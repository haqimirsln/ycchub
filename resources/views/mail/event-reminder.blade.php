<x-mail::message>
# See you {{ $when }}, {{ $name }}!

Just a reminder that you're registered for **{{ $program->title }}**.

<x-mail::panel>
**When:** {{ $program->starts_at->format('l, j M Y, g:i A') }}@if ($program->ends_at) to {{ $program->ends_at->isSameDay($program->starts_at) ? $program->ends_at->format('g:i A') : $program->ends_at->format('j M, g:i A') }}@endif<br>
@if ($program->isOnline())
**Where:** Online ({{ $program->meetingPlatform() }}). The join link shows on the event page from {{ $program->joinOpensAt()->format('g:i A') }}.
@else
**Where:** {{ $program->venue }}@if ($program->mapUrl()) · [Open in Google Maps]({{ $program->mapUrl() }})@endif
@if ($program->hasMeeting())
<br>Joining online instead? The link shows on the event page from {{ $program->joinOpensAt()->format('g:i A') }}.
@endif
@endif
</x-mail::panel>

<x-mail::button :url="$eventUrl">
View event
</x-mail::button>

@if (! $program->isOnline())
**On the day:** scan the QR code the MT shows at the venue to check in. That's what counts your attendance and merit.
@else
**During the session:** scan the QR code the MT shares on screen to check in. That's what counts your attendance and merit.
@endif

[Add to Google Calendar]({{ $calendarUrl }})

**Can't make it any more?** Please cancel on the [event page]({{ $eventUrl }}) so someone else can have your seat.

See you there,<br>
The YCC MT

<x-slot:subcopy>
You're getting this because you registered for this event in YCC Hub.
</x-slot:subcopy>
</x-mail::message>
