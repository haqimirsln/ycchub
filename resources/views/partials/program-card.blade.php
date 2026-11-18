{{-- $p: Event (withSeatCounts), $reg: ?ProgramRegistration for the current user --}}
@php
    $isRegistered = $reg && in_array($reg->status, ['registered', 'attended', 'absent'], true);
    $cancelled = $p->status === 'cancelled';
@endphp
<article class="panel flex flex-col overflow-hidden {{ $cancelled ? 'opacity-75' : '' }}"
         x-data="{ seats: {{ $p->seatsTaken() }} }" @seat-changed="seats += $event.detail.delta">
    @if ($thumb = $p->posterThumbUrl())
        {{-- Whole poster stays readable: shown uncropped over a blurred copy of itself. --}}
        <a href="{{ route('programs.show', $p) }}" class="relative block aspect-[16/10] overflow-hidden bg-abyss-900" tabindex="-1" aria-hidden="true">
            <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full scale-110 object-cover opacity-50 blur-xl">
            <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" class="relative mx-auto h-full w-auto max-w-full object-contain shadow-lg">
        </a>
    @endif
    <a href="{{ route('programs.show', $p) }}" class="flex gap-4 p-4 hover:bg-glass-50">
        <div class="w-14 shrink-0 text-center">
            <div class="font-display text-4xl font-extrabold leading-none text-abyss-900">{{ $p->starts_at->format('j') }}</div>
            <div class="text-xs font-bold text-ink-soft">{{ $p->starts_at->format('M') }}</div>
            <div class="text-xs text-ink-soft">{{ $p->starts_at->format('D') }}</div>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold text-kelp-500">{{ $p->categoryLabel() }}</p>
            <h3 class="font-sans text-base font-bold leading-snug text-ink">{{ $p->title }}</h3>
            <p class="mt-1 flex items-center gap-1 text-sm text-ink-soft"><x-icon name="clock" class="size-3.5 shrink-0" />{{ $p->starts_at->format('g:i A') }}</p>
            <p class="flex items-start gap-1 text-sm text-ink-soft"><x-icon :name="$p->isOnline() ? 'video' : 'pin'" class="mt-0.5 size-3.5 shrink-0" /><span class="line-clamp-1">{{ $p->whereLabel() }}</span></p>
        </div>
    </a>

    <div class="mt-auto border-t border-glass-100 px-4 py-3">
        @if ($p->max_capacity)
            <div class="mb-3">
                <div class="flex justify-between text-xs text-ink-soft">
                    <span x-text="`${seats} of {{ $p->max_capacity }} seats taken`">{{ $p->seatsTaken() }} of {{ $p->max_capacity }} seats taken</span>
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-glass-100">
                    <div class="h-full rounded-full bg-abyss-700 transition-all" :style="`width: ${Math.min(100, seats / {{ $p->max_capacity }} * 100)}%`" style="width: {{ $p->capacityPercent() }}%"></div>
                </div>
            </div>
        @endif

        @if ($cancelled)
            <x-status-badge status="cancelled" />
            <span class="ml-1 text-sm text-ink-soft">This event was cancelled.</span>
        @elseif ($p->isRegistrationOpen() && (! $p->isFull() || $isRegistered))
            <div x-data="joinButton({ registered: @js($isRegistered), registerUrl: @js(route('programs.register', $p)), cancelUrl: @js(route('programs.cancel', $p)) })" class="flex items-center justify-between gap-2">
                <span x-show="registered" class="inline-flex items-center gap-1 text-sm font-bold text-st-active"><x-icon name="check" class="size-4" /> You're registered</span>
                <span x-show="!registered" class="text-sm text-ink-soft">Registration closes {{ $p->registrationCloseTime()->format('j M, g:i A') }}</span>
                <button type="button" @click="toggle" :disabled="loading"
                        class="btn btn-sm shrink-0" :class="registered ? 'border border-glass-300 bg-white text-ink-soft hover:text-st-inactive' : 'bg-crest-500 text-abyss-950 hover:bg-crest-400'">
                    <span x-text="registered ? 'Cancel' : 'Register'">{{ $isRegistered ? 'Cancel' : 'Register' }}</span>
                </button>
            </div>
        @elseif ($isRegistered)
            <span class="inline-flex items-center gap-1 text-sm font-bold text-st-active"><x-icon name="check" class="size-4" /> You're registered</span>
        @elseif ($p->isFull())
            <span class="text-sm font-bold text-st-inactive">Full</span>
        @else
            <span class="text-sm text-ink-soft">Registration closed</span>
        @endif
    </div>
</article>
