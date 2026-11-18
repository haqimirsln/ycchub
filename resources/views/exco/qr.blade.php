<x-layouts.base :title="'Check in: '.$program->title" body-class="bg-abyss-900 text-white">
    <div class="flex min-h-dvh flex-col items-center justify-center px-6 py-8 text-center">
        <img src="{{ asset('images/ycc-crest-192.png') }}" alt="" class="size-14">
        <h1 class="mt-3 max-w-3xl text-4xl font-extrabold leading-none sm:text-6xl">{{ $program->title }}</h1>
        <p class="mt-2 text-glass-300">Scan with your phone camera, log in, then tap “I'm here”.</p>

        <div class="mt-6 rounded-xl bg-white p-4">
            <canvas id="checkin-qr" data-url="{{ $checkinUrl }}" data-status-url="{{ route('exco.attendance.show', $program) }}"></canvas>
        </div>

        <p class="mt-6 font-display text-6xl font-extrabold text-crest-400"><span id="checkin-count">{{ $counts['attended'] }}</span> <span class="text-3xl text-glass-300">checked in</span></p>
        <p id="checkin-closed" class="mt-3 rounded-md bg-st-risk px-4 py-2 font-bold text-abyss-950 {{ $program->checkin_open ? 'hidden' : '' }}">Check-in is closed. Open it from the attendance page.</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <form method="POST" action="{{ route('exco.attendance.checkin-toggle', $program) }}">
                @csrf <button class="btn-ghost-light">{{ $program->checkin_open ? 'Close check-in' : 'Open check-in' }}</button>
            </form>
            <a href="{{ route('exco.attendance.show', $program) }}" class="btn-ghost-light">Back to attendance</a>
        </div>
        <p class="mt-6 break-all text-xs text-glass-300">{{ $checkinUrl }}</p>
    </div>
    @push('scripts')
        @vite('resources/js/qr.js')
    @endpush
</x-layouts.base>
