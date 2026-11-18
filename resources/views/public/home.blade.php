<x-layouts.public>
    @php($next = $upcoming->first())

    {{-- Hero: fills exactly one screen below the header on every device. --}}
    <section class="relative overflow-hidden">
        <div class="mx-auto grid min-h-[calc(100svh-var(--header-h))] max-w-6xl grid-cols-[minmax(0,1fr)] content-center items-center gap-x-10 gap-y-4 px-4 py-6 sm:gap-y-6 sm:px-6 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] md:gap-y-8 md:py-10">
            <div class="order-2 md:order-1">
                <h1 class="text-[clamp(2.25rem,min(10.5vw,7.4svh),5.5rem)] font-extrabold leading-[0.95] text-white">
                    Students looking after Terengganu's coast since 2017.
                </h1>
                <p class="mt-4 max-w-xl text-[0.95rem] leading-relaxed text-glass-200 sm:mt-6 sm:text-lg">
                    <span class="sm:hidden">Beach cleanups, hatchery trips, reef surveys and talks, run by students at UMT.</span>
                    <span class="hidden sm:inline">The Young Conservationist Club at UMT runs beach cleanups, hatchery trips, reef surveys and talks.
                    YCC Hub is where members sign up for events and see their own track record.</span>
                </p>
                <div class="mt-5 flex flex-wrap gap-3 sm:mt-8">
                    @auth
                        <a href="{{ route('home.redirect') }}" class="btn-gold px-6 py-3 text-base">Open my hub</a>
                    @else
                        <a href="{{ route('register') }}" class="btn-gold px-6 py-3 text-base">Join the club</a>
                        <a href="{{ route('login') }}" class="btn-ghost-light px-6 py-3 text-base">Log in</a>
                    @endauth
                </div>

                @if ($next)
                    <a href="{{ auth()->check() ? route('programs.show', $next) : route('login') }}"
                       class="group mt-5 flex max-w-xl items-stretch overflow-hidden rounded-lg border border-white/15 bg-abyss-800/60 transition-colors duration-150 hover:border-crest-500 sm:mt-10">
                        <div class="flex w-20 shrink-0 flex-col items-center justify-center bg-crest-500 text-abyss-950">
                            <span class="font-display text-4xl font-extrabold leading-none">{{ $next->starts_at->format('j') }}</span>
                            <span class="text-sm font-bold">{{ $next->starts_at->format('M') }}</span>
                        </div>
                        <div class="min-w-0 px-4 py-3">
                            <p class="text-sm text-crest-300">Next event</p>
                            <p class="truncate font-bold text-white transition-colors duration-150 group-hover:text-crest-300">{{ $next->title }}</p>
                            <p class="truncate text-sm text-glass-300">{{ $next->starts_at->format('l, g:i A') }}, {{ $next->whereLabel() }}</p>
                        </div>
                        @if ($nextThumb = $next->posterThumbUrl())
                            <div class="relative ml-auto w-16 shrink-0 sm:w-20" aria-hidden="true">
                                <img src="{{ $nextThumb }}" alt="" decoding="async" class="absolute inset-0 size-full object-cover transition-transform duration-200 group-hover:scale-105">
                            </div>
                        @endif
                    </a>
                @endif
            </div>

            <div class="order-1 flex justify-center py-3 md:order-2 md:py-0 [@media(max-height:620px)_and_(max-width:767px)]:hidden">
                <x-crest class="w-20 sm:w-36 md:w-[min(100%,26rem,56svh)]"
                         alt="Young Conservationist Club crest: a seahorse, coral, sea turtle and whale shark" />
            </div>
        </div>
    </section>

    {{-- The club in one sentence --}}
    @if ($numbers['members'] > 0)
        <section class="border-y border-white/10 bg-abyss-950">
            <p class="mx-auto max-w-5xl px-4 py-12 font-display text-3xl font-semibold leading-tight text-glass-300 sm:px-6 sm:text-5xl">
                <strong class="font-extrabold text-white">{{ number_format($numbers['members']) }} members</strong> have turned up
                <strong class="font-extrabold text-white">{{ number_format($numbers['attendances']) }} times</strong> across
                <strong class="font-extrabold text-white">{{ number_format($numbers['programs']) }} events</strong>.
            </p>
        </section>
    @endif

    {{-- Upcoming events, laid out like a tide table --}}
    <section id="programs" class="scroll-mt-6 bg-glass-50 text-ink">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-5xl font-extrabold text-abyss-900">Coming up</h2>
                    <p class="mt-2 max-w-xl text-ink-soft">Members register in the hub. Seats are first come, first served.</p>
                </div>
                @auth
                    <a href="{{ route('programs.index') }}" class="btn-line">All events</a>
                @endauth
            </div>

            @if ($upcoming->isEmpty())
                <div class="mt-8 rounded-lg border border-dashed border-glass-300 bg-white px-6 py-10 text-center text-ink-soft">
                    Nothing is scheduled yet. New events are announced on Instagram first.
                </div>
            @else
                <ol class="mt-8 divide-y divide-glass-200 overflow-hidden rounded-lg border border-glass-200 bg-white">
                    @foreach ($upcoming as $p)
                        @php($thumb = $p->posterThumbUrl())
                        <li class="grid gap-4 p-4 sm:items-center sm:p-5 {{ $thumb ? 'grid-cols-[4.5rem_minmax(0,1fr)_4rem] sm:grid-cols-[5.5rem_5rem_minmax(0,1fr)_auto]' : 'grid-cols-[4.5rem_minmax(0,1fr)] sm:grid-cols-[5.5rem_minmax(0,1fr)_auto]' }}">
                            <div class="text-center">
                                <div class="font-display text-5xl font-extrabold leading-none text-abyss-900">{{ $p->starts_at->format('j') }}</div>
                                <div class="text-sm font-bold text-ink-soft">{{ $p->starts_at->format('M') }} &middot; {{ $p->starts_at->format('D') }}</div>
                            </div>
                            @if ($thumb)
                                <a href="{{ auth()->check() ? route('programs.show', $p) : route('register') }}" class="col-start-3 row-start-1 block self-start sm:col-start-2" tabindex="-1" aria-hidden="true">
                                    <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" class="aspect-[4/5] w-full rounded-md border border-glass-200 bg-abyss-900 object-contain shadow-sm transition-transform duration-200 hover:scale-[1.03]">
                                </a>
                            @endif
                            <div class="col-start-2 row-start-1 min-w-0 {{ $thumb ? 'sm:col-start-3' : '' }}">
                                <p class="text-sm font-bold text-kelp-500">{{ $p->categoryLabel() }}</p>
                                <h3 class="font-sans text-lg font-bold leading-snug text-ink">{{ $p->title }}</h3>
                                <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink-soft">
                                    <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-4" />{{ $p->starts_at->format('g:i A') }}</span>
                                    <span class="inline-flex items-center gap-1"><x-icon :name="$p->isOnline() ? 'video' : 'pin'" class="size-4" />{{ $p->whereLabel() }}</span>
                                </p>
                            </div>
                            <div class="col-start-2 flex items-center gap-3 sm:row-start-1 sm:flex-col sm:items-end {{ $thumb ? 'col-span-2 sm:col-span-1 sm:col-start-4' : 'sm:col-start-3' }}">
                                @if ($p->max_capacity)
                                    <span class="text-sm {{ $p->isFull() ? 'font-bold text-st-inactive' : 'text-ink-soft' }}">
                                        {{ $p->isFull() ? 'Full' : $p->seatsLeft().' of '.$p->max_capacity.' seats left' }}
                                    </span>
                                @endif
                                <a href="{{ auth()->check() ? route('programs.show', $p) : route('register') }}" class="btn-dark btn-sm">
                                    {{ auth()->check() ? 'View event' : 'Join to register' }}
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>

    {{-- Instagram --}}
    <section id="instagram" class="scroll-mt-6 overflow-hidden bg-abyss-900 py-16">
        <div class="mx-auto flex max-w-6xl flex-wrap items-end justify-between gap-4 px-4 sm:px-6">
            <div>
                <h2 class="text-5xl font-extrabold text-white">Fresh from the field</h2>
                <p class="mt-2 text-glass-300">Latest posts from <a href="{{ $social['instagram_url'] }}" target="_blank" rel="noopener" class="text-crest-300 underline decoration-crest-500/40 underline-offset-4 hover:decoration-crest-300">{{ '@'.$social['instagram_handle'] }}</a></p>
            </div>
            <a href="{{ $social['instagram_url'] }}" target="_blank" rel="noopener" class="btn-gold">
                <x-icon name="instagram" class="size-4" /> Follow on Instagram
            </a>
        </div>

        @php($apiPosts = $posts->where('source', 'api')->values())
        @php($manualPosts = $posts->where('source', 'manual')->values())

        @if ($apiPosts->count() >= 6)
            {{-- Two slow, opposite rows. Pauses on hover/focus; static for reduced motion. --}}
            @php($rows = [$apiPosts->take(5)->values(), $apiPosts->slice(5)->values()->isEmpty() ? $apiPosts->reverse()->values() : $apiPosts->slice(5)->values()])
            <div class="mt-10 space-y-4">
                @foreach ($rows as $i => $row)
                    <div class="tide tide-row {{ $i === 1 ? 'tide-reverse' : '' }}" style="--tide-speed: {{ 60 + $i * 15 }}s">
                        <ul class="tide-track flex gap-4 px-4">
                            @foreach ([$row, $row] as $copy => $set)
                                @foreach ($set as $post)
                                    <li class="shrink-0" @if ($copy === 1) aria-hidden="true" @endif>
                                        @include('public.partials.ig-tile', ['post' => $post, 'tabbable' => $copy === 0])
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @elseif ($apiPosts->isNotEmpty())
            <ul class="mx-auto mt-10 grid max-w-6xl grid-cols-2 gap-4 px-4 sm:grid-cols-3 sm:px-6">
                @foreach ($apiPosts as $post)
                    <li>@include('public.partials.ig-tile', ['post' => $post, 'tabbable' => true, 'fluid' => true])</li>
                @endforeach
            </ul>
        @elseif ($manualPosts->isNotEmpty())
            {{-- Instagram's own embed, with the post caption. Columns let tall and short posts pack neatly. --}}
            <div class="mx-auto mt-10 max-w-6xl columns-1 gap-6 px-4 sm:px-6 md:columns-2 lg:columns-3">
                @foreach ($manualPosts->take(6) as $post)
                    <div class="mb-6 break-inside-avoid">
                        <blockquote class="instagram-media !m-0 !w-full !min-w-0 !max-w-none overflow-hidden !rounded-lg" data-instgrm-permalink="{{ $post->permalink }}" data-instgrm-captioned data-instgrm-version="14">
                            <a href="{{ $post->permalink }}" target="_blank" rel="noopener" class="block bg-white p-6 text-center text-sm text-ink">View this post on Instagram</a>
                        </blockquote>
                    </div>
                @endforeach
            </div>
            @push('scripts')
                <script async src="https://www.instagram.com/embed.js"></script>
            @endpush
        @else
            <div class="mx-auto mt-10 max-w-6xl px-4 sm:px-6">
                <a href="{{ $social['instagram_url'] }}" target="_blank" rel="noopener"
                   class="flex flex-col items-start gap-3 rounded-lg border border-dashed border-white/20 px-6 py-10 text-glass-300 hover:border-crest-500 sm:flex-row sm:items-center">
                    <x-icon name="instagram" class="size-8 text-crest-400" />
                    <span>Photos from every cleanup, hatchery trip and talk go up on Instagram. Follow {{ '@'.$social['instagram_handle'] }} to see them first.</span>
                </a>
            </div>
        @endif
    </section>

    {{-- How to join (a real sequence, so it's numbered) + LinkedIn --}}
    @if ($mt && $mt['terms']->isNotEmpty())
        <section id="mt" class="scroll-mt-6 bg-white text-ink">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="font-bold text-kelp-500">Majlis Tertinggi, Sesi {{ $mt['session'] }}</p>
                        <h2 class="text-5xl font-extrabold text-abyss-900">Meet the MT</h2>
                    </div>
                    @if ($mt['advisor'])<p class="text-ink-soft"><span class="font-bold text-ink">Penasihat:</span> {{ $mt['advisor'] }}</p>@endif
                </div>
                <ul class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($mt['terms'] as $t)
                        <li class="text-center">
                            @if ($t->user && $t->user->avatarUrl())
                                <img src="{{ $t->user->avatarUrl() }}" alt="" class="mx-auto size-24 rounded-full object-cover ring-4 ring-crest-300/40" loading="lazy">
                            @else
                                <span class="mx-auto flex size-24 items-center justify-center rounded-full bg-abyss-900 font-display text-3xl font-extrabold text-crest-300 ring-4 ring-crest-300/40">{{ \Illuminate\Support\Str::of($t->name())->explode(' ')->filter(fn ($w) => ! in_array(strtolower($w), ['bin', 'binti', 'bt', 'a/l', 'a/p'], true))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                            @endif
                            <p class="mt-3 text-xs font-bold uppercase tracking-wide text-kelp-500">{{ $t->label() }}</p>
                            <p class="mt-0.5 text-sm font-bold leading-snug text-ink">{{ $t->displayName() }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section id="join" class="scroll-mt-6 bg-glass-50 text-ink">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.4fr_1fr]">
            <div>
                <h2 class="text-5xl font-extrabold text-abyss-900">How joining works</h2>
                <ol class="mt-8 space-y-6">
                    @foreach ([
                        ['Sign up with your matric number', 'Takes two minutes. Use your UMT details so the MT can verify you.'],
                        ['Wait for MT approval', 'Usually within a few days. You get an email once you are in.'],
                        ['Register for events', 'Pick what interests you. Cancel before the day if plans change, so someone else can take the seat.'],
                        ['Check in on the day', 'Scan the QR code the MT shows at the venue. Your attendance record updates straight away.'],
                    ] as $i => [$step, $detail])
                        <li class="grid grid-cols-[3rem_1fr] gap-4">
                            <span class="flex size-12 items-center justify-center rounded-full border-2 border-crest-500 font-display text-2xl font-extrabold text-abyss-900">{{ $i + 1 }}</span>
                            <div>
                                <h3 class="font-sans text-lg font-bold text-ink">{{ $step }}</h3>
                                <p class="text-ink-soft">{{ $detail }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @guest
                    <a href="{{ route('register') }}" class="btn-dark mt-8 px-6 py-3 text-base">Create my account</a>
                @endguest
            </div>

            <aside class="self-start rounded-lg bg-abyss-900 p-6 text-white sm:p-8">
                <x-icon name="linkedin" class="size-9 text-crest-400" />
                <h2 class="mt-4 text-3xl font-extrabold">Partners and sponsors</h2>
                <p class="mt-3 text-glass-300">
                    Working with YCC on a cleanup, a talk or a field trip? Event reports and partnership news are posted on our LinkedIn page.
                </p>
                <a href="{{ $social['linkedin_url'] }}" target="_blank" rel="noopener" class="btn-gold mt-6">
                    Follow YCC on LinkedIn <x-icon name="external" class="size-4" />
                </a>
            </aside>
        </div>
    </section>
</x-layouts.public>
