<x-layouts.app title="Check in">
    <div class="mx-auto max-w-md">
        <div class="panel overflow-hidden text-center">
            <div class="bg-abyss-900 px-6 py-6 text-white">
                <p class="text-sm text-crest-300">{{ $program->starts_at->format('l, j M') }}</p>
                <h1 class="mt-1 text-4xl font-extrabold leading-none">{{ $program->title }}</h1>
                <p class="mt-2 text-sm text-glass-300">{{ $program->whereLabel() }}</p>
            </div>
            <div class="p-6">
                @if ($registration && $registration->status === 'attended')
                    <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-st-active text-white">
                        <x-icon name="check" class="size-10" />
                    </div>
                    <p class="mt-4 text-xl font-bold">You're checked in</p>
                    <p class="mt-1 text-ink-soft">at {{ $registration->checked_in_at?->format('g:i A') }}. Enjoy the event!</p>
                    <a href="{{ route('portal') }}" class="btn-line mt-6 w-full">Back to my hub</a>
                @else
                    <p class="text-ink-soft">
                        {{ $registration && $registration->status === 'registered'
                            ? 'You registered for this event. Tap below to confirm you\'re here.'
                            : 'You didn\'t register beforehand, but you can still check in as a walk-in.' }}
                    </p>
                    <form method="POST" action="{{ route('checkin.store', $program->checkin_code) }}" class="mt-6">
                        @csrf
                        <button type="submit" class="btn-gold w-full py-4 text-lg">I'm here, check me in</button>
                    </form>
                    @unless ($program->checkin_open)
                        <p class="mt-4 text-sm text-st-risk">Check-in isn't open yet. Wait for the MT to open it, then tap the button.</p>
                    @endunless
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
