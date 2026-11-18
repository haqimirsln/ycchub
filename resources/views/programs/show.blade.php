<x-layouts.app :title="$program->title">
    @php
        $isRegistered = $registration && in_array($registration->status, ['registered', 'attended', 'absent'], true);
        $u = auth()->user();
    @endphp

    <x-page-header :title="$program->title" :back="[route('programs.index'), 'Events']">
        @can('exco')
            <x-slot:actions>
                <a href="{{ route('exco.programs.edit', $program) }}" class="btn-line"><x-icon name="edit" class="size-4" /> Edit</a>
                <a href="{{ route('exco.attendance.show', $program) }}" class="btn-dark"><x-icon name="check" class="size-4" /> Attendance</a>
                <a href="{{ route('exco.programs.report', $program) }}" class="btn-line" data-no-prefetch><x-icon name="file" class="size-4" /> Event report</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    @if ($program->status !== 'published')
        <div class="mb-6 flex items-center gap-3 rounded-lg border border-st-risk/30 bg-white p-4">
            <x-status-badge :status="$program->status" />
            <p class="text-sm text-ink-soft">
                {{ $program->status === 'draft' ? 'Only the MT can see this draft. Edit it and choose Publish when it\'s ready.' : 'This event was cancelled.' }}
            </p>
        </div>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            @if ($program->posterUrl())
                <div x-data="{ zoom: false }" @keydown.escape.window="zoom = false">
                    <button type="button" @click="zoom = true" class="group relative block w-full overflow-hidden rounded-lg border border-glass-200 bg-abyss-900" aria-label="View poster full screen">
                        <img src="{{ $program->posterUrl() }}" alt="" aria-hidden="true" class="absolute inset-0 size-full scale-110 object-cover opacity-40 blur-2xl">
                        <img src="{{ $program->posterUrl() }}" alt="Poster for {{ $program->title }}" class="relative mx-auto max-h-[70svh] w-auto object-contain lg:max-h-[32rem]">
                        <span class="absolute bottom-3 right-3 rounded-md bg-abyss-950/70 px-2.5 py-1 text-xs font-bold text-white opacity-90 transition-opacity group-hover:opacity-100">Tap to view full size</span>
                    </button>
                    <div x-cloak x-show="zoom" x-transition.opacity.duration.150ms @click="zoom = false"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-abyss-950/90 p-4" role="dialog" aria-modal="true" aria-label="Poster for {{ $program->title }}">
                        <img src="{{ $program->posterUrl() }}" alt="Poster for {{ $program->title }}" class="max-h-full max-w-full rounded-md object-contain shadow-2xl">
                        <button type="button" class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white text-abyss-900 shadow-lg" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                    </div>
                </div>
            @endif
            <div class="panel p-5 sm:p-6">
                <p class="text-sm font-bold text-kelp-500">{{ $program->categoryLabel() }}</p>
                <div class="prose-ycc mt-3 max-w-prose text-[1.02rem] leading-relaxed text-ink">
                    @foreach (preg_split('/\n\s*\n/', trim($program->description)) as $para)
                        <p>{!! nl2br(e($para)) !!}</p>
                    @endforeach
                </div>
                @unless ($program->counts_for_activity)
                    <p class="mt-4 text-sm text-ink-soft">This event doesn't count toward your activity status.</p>
                @endunless
            </div>

            @if ($committee->isNotEmpty() || ($program->committee_count ?? null))
                <div id="committee" class="panel scroll-mt-6 p-5 sm:p-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-3xl font-extrabold text-abyss-900">Committee</h2>
                        @if ($program->committee_count)<p class="text-sm text-ink-soft"><span class="font-bold text-ink">{{ $program->committee_count }}</span> AJK in total</p>@endif
                    </div>
                    @if ($committee->isNotEmpty())
                        <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                            @foreach ($committee as $c)
                                <li class="flex items-center gap-3 rounded-md bg-glass-50 px-3 py-2.5">
                                    @if ($c->user)<x-avatar :user="$c->user" />@else<span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-glass-100 text-ink-soft"><x-icon name="users" class="size-4" /></span>@endif
                                    <span class="min-w-0">
                                        <span class="block text-xs font-bold uppercase tracking-wide text-kelp-500">{{ $c->position }}</span>
                                        <span class="block truncate font-bold">{{ $c->user?->full_name ?? 'To be decided' }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            {{-- Member feedback: only for people who attended, from the start until 30 days after --}}
            @if ($feedbackReady && $registration && $registration->status === 'attended' && ($program->isFeedbackOpen() || $myFeedback))
                <div id="feedback" class="panel scroll-mt-6 p-5 sm:p-6" x-data="{ rating: {{ (int) old('rating', $myFeedback?->rating ?? 0) }}, hover: 0, editing: {{ $myFeedback && ! $errors->has('rating') ? 'false' : 'true' }} }">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-3xl font-extrabold text-abyss-900">How was it?</h2>
                        <p class="text-sm text-ink-soft">Only the MT reads this, and your name isn't shown.</p>
                    </div>

                    @if ($myFeedback)
                        <div x-show="!editing" class="mt-3">
                            <p class="flex items-center gap-1 text-crest-500" aria-label="You rated it {{ $myFeedback->rating }} out of 5">
                                @for ($i = 1; $i <= 5; $i++)<x-icon name="star" class="size-6 {{ $i <= $myFeedback->rating ? 'fill-current' : 'text-glass-300' }}" />@endfor
                                <span class="ml-2 text-sm font-bold text-ink">Your rating</span>
                            </p>
                            @if ($myFeedback->comment)<p class="mt-2 text-ink-soft">“{{ $myFeedback->comment }}”</p>@endif
                            @if ($program->isFeedbackOpen())
                                <button type="button" class="mt-3 text-sm font-bold text-kelp-500 hover:underline" @click="editing = true">Change my feedback</button>
                            @endif
                        </div>
                    @endif

                    @if ($program->isFeedbackOpen())
                        <form method="POST" action="{{ route('programs.feedback', $program) }}" class="mt-4 space-y-4" x-show="editing" @if ($myFeedback) x-cloak @endif>
                            @csrf
                            <fieldset>
                                <legend class="sr-only">Rating from 1 to 5 stars</legend>
                                <div class="flex items-center gap-1" @mouseleave="hover = 0">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <label class="cursor-pointer rounded-md p-1 transition-transform active:scale-90" @mouseenter="hover = {{ $i }}">
                                            <input type="radio" name="rating" value="{{ $i }}" class="sr-only" x-model.number="rating">
                                            <x-icon name="star" class="size-9 transition-colors" ::class="(hover || rating) >= {{ $i }} ? 'fill-current text-crest-500' : 'text-glass-300'" />
                                            <span class="sr-only">{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</span>
                                        </label>
                                    @endfor
                                    <span class="ml-2 text-sm font-bold text-ink-soft" x-text="['', 'Not great', 'Okay', 'Good', 'Great', 'Loved it'][hover || rating]"></span>
                                </div>
                                @error('rating') <p class="field-error">{{ $message }}</p> @enderror
                            </fieldset>
                            <div>
                                <label for="comment" class="field-label">Anything to add? <span class="font-normal text-ink-soft">(optional)</span></label>
                                <textarea id="comment" name="comment" rows="3" maxlength="1000" class="field" placeholder="What went well, what should we change next time?">{{ old('comment', $myFeedback?->comment) }}</textarea>
                                @error('comment') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="btn-gold" :disabled="!rating">Send feedback</button>
                                @if ($myFeedback)<button type="button" class="btn-line" @click="editing = false">Keep it</button>@endif
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            {{-- What members said: MT only --}}
            @if ($feedbackReady && auth()->user()->isExco() && $program->starts_at->isPast())
                @php $avg = $feedback->avg('rating'); @endphp
                <div id="feedback-summary" class="panel scroll-mt-6 p-5 sm:p-6">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 class="text-3xl font-extrabold text-abyss-900">What members said</h2>
                        @if ($feedback->isNotEmpty())
                            <p class="flex items-center gap-2"><x-icon name="star" class="size-6 fill-current text-crest-500" /><span class="font-display text-4xl font-extrabold leading-none text-abyss-900">{{ number_format($avg, 1) }}</span><span class="text-sm text-ink-soft">from {{ $feedback->count() }} {{ \Illuminate\Support\Str::plural('member', $feedback->count()) }}</span></p>
                        @endif
                    </div>
                    @if ($feedback->isEmpty())
                        <p class="mt-2 text-ink-soft">No feedback yet. Members who attended can rate it from this page for 30 days after it starts.</p>
                    @else
                        <div class="mt-4 space-y-1.5">
                            @for ($i = 5; $i >= 1; $i--)
                                @php $n = $feedback->where('rating', $i)->count(); @endphp
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="w-10 shrink-0 text-ink-soft">{{ $i }} ★</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-glass-100"><div class="h-full rounded-full bg-crest-500" style="width: {{ round($n / $feedback->count() * 100) }}%"></div></div>
                                    <span class="w-6 shrink-0 text-right tabular-nums text-ink-soft">{{ $n }}</span>
                                </div>
                            @endfor
                        </div>
                        @php $comments = $feedback->whereNotNull('comment'); @endphp
                        @if ($comments->isNotEmpty())
                            <ul class="mt-5 space-y-3">
                                @foreach ($comments as $f)
                                    <li class="rounded-md bg-glass-50 px-4 py-3">
                                        <p class="text-sm text-crest-600">@for ($i = 1; $i <= 5; $i++){{ $i <= $f->rating ? '★' : '☆' }}@endfor <span class="ml-1 text-ink-soft">{{ $f->created_at->diffForHumans() }}</span></p>
                                        <p class="mt-1 text-ink">{{ $f->comment }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <div class="panel divide-y divide-glass-100">
                <div class="flex gap-3 p-4">
                    <x-icon name="calendar" class="size-5 shrink-0 text-kelp-500" />
                    <div>
                        <p class="font-bold">{{ $program->starts_at->format('l, j F Y') }}</p>
                        <p class="text-sm text-ink-soft">
                            {{ $program->starts_at->format('g:i A') }}@if ($program->ends_at) to {{ $program->ends_at->isSameDay($program->starts_at) ? $program->ends_at->format('g:i A') : $program->ends_at->format('j M, g:i A') }}@endif
                        </p>
                        @if ($program->counts_for_activity && ($hours = $program->volunteerHours()) > 0)
                            <p class="mt-1 text-sm text-ink-soft">Counts as <span class="font-bold text-ink">{{ rtrim(rtrim(number_format($hours, 1), '0'), '.') }} volunteer {{ $hours == 1 ? 'hour' : 'hours' }}</span></p>
                        @endif
                        @if ($program->status !== 'cancelled' && ! $program->calendarEndsAt()->isPast())
                            <p class="mt-3 flex items-center gap-1 text-xs font-bold uppercase tracking-wide text-ink-soft"><x-icon name="calendar-plus" class="size-4" /> Add to calendar</p>
                            <div class="mt-1.5 grid grid-cols-2 gap-2">
                                <a href="{{ $program->googleCalendarUrl() }}" target="_blank" rel="noopener" class="btn-line btn-sm">Google</a>
                                <a href="{{ route('programs.calendar', $program) }}" data-no-prefetch download class="btn-line btn-sm">Apple / Outlook</a>
                            </div>
                        @endif
                    </div>
                </div>
                @if ($program->hasPlace())
                    <div class="p-4">
                        <div class="flex gap-3">
                            <x-icon name="pin" class="size-5 shrink-0 text-kelp-500" />
                            <div class="min-w-0">
                                <p class="font-bold">{{ $program->venue }}</p>
                                <p class="mt-0.5 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                                    <a href="{{ $program->mapUrl() }}" target="_blank" rel="noopener" class="font-bold text-kelp-500 underline-offset-4 hover:underline">Open in Google Maps</a>
                                    <a href="{{ $program->directionsUrl() }}" target="_blank" rel="noopener" class="font-bold text-kelp-500 underline-offset-4 hover:underline">Directions</a>
                                </p>
                            </div>
                        </div>
                        @if ($embed = $program->mapEmbedUrl())
                            <div class="relative z-0 mt-3 h-48 overflow-hidden rounded-md border border-glass-200 bg-glass-100">
                                <iframe src="{{ $embed }}" class="size-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map of {{ $program->venue }}" allowfullscreen></iframe>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($program->hasMeeting())
                    @php
                        $canJoin = auth()->user()->isExco() || ($registration && in_array($registration->status, ['registered', 'attended'], true));
                        $platform = $program->meetingPlatform();
                    @endphp
                    <div class="p-4" x-data="{ justRegistered: false }" @seat-changed.window="justRegistered = $event.detail.delta > 0">
                        <div class="flex gap-3">
                            <x-icon name="video" class="size-5 shrink-0 text-kelp-500" />
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">{{ $program->hasPlace() ? 'Also online' : 'Online' }} on {{ $platform }}</p>

                                @if ($program->status === 'cancelled')
                                    <p class="text-sm text-ink-soft">Cancelled, so there's no session to join.</p>
                                @elseif ($canJoin && auth()->user()->isExco())
                                    @if ($program->meeting_url)
                                        <a href="{{ $program->meeting_url }}" target="_blank" rel="noopener" class="btn-dark btn-sm mt-2 w-full"><x-icon name="external" class="size-4" /> Join on {{ $platform }}</a>
                                        <p class="mt-2 text-xs text-ink-soft">The MT always sees this. Registered members see the button from {{ $program->joinOpensAt()->format('g:i A, j M') }}.</p>
                                    @else
                                        <p class="mt-1 text-sm text-st-risk">No meeting link yet. <a href="{{ route('exco.programs.edit', $program) }}" class="font-bold underline">Add it</a> before the event starts.</p>
                                    @endif
                                @elseif ($canJoin)
                                    @if ($program->isJoinOver())
                                        <p class="text-sm text-ink-soft">This online session has ended.</p>
                                    @elseif (! $program->meeting_url)
                                        <p class="text-sm text-ink-soft">The MT will add the meeting link here before it starts.</p>
                                    @elseif ($program->isJoinOpen())
                                        <a href="{{ $program->meeting_url }}" target="_blank" rel="noopener" class="btn-gold mt-2 w-full"><x-icon name="external" class="size-4" /> Join on {{ $platform }}</a>
                                    @else
                                        <p class="text-sm text-ink-soft">The Join button shows up here at <span class="font-bold text-ink">{{ $program->joinOpensAt()->format('g:i A') }}</span> on {{ $program->joinOpensAt()->format('j M') }}, 30 minutes before the start.</p>
                                    @endif
                                @else
                                    <p class="text-sm text-ink-soft" x-show="!justRegistered">Register to get the join link.</p>
                                    <p class="text-sm" x-cloak x-show="justRegistered">You're in. <button type="button" class="font-bold text-kelp-500 underline" onclick="location.reload()">Refresh</button> to see the join details.</p>
                                @endif

                                @if ($canJoin && $program->meeting_info && $program->status !== 'cancelled' && ! $program->isJoinOver())
                                    <p class="mt-2 rounded-md bg-glass-50 px-3 py-2 text-sm text-ink">{{ $program->meeting_info }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
                @if ($program->max_capacity)
                    <div class="p-4" x-data="{ seats: {{ $program->seatsTaken() }} }" @seat-changed.window="seats += $event.detail.delta">
                        <div class="flex items-baseline justify-between">
                            <p class="font-bold"><span x-text="seats">{{ $program->seatsTaken() }}</span> / {{ $program->max_capacity }} seats</p>
                            <p class="text-sm text-ink-soft"><span x-text="Math.max(0, {{ $program->max_capacity }} - seats)">{{ $program->seatsLeft() }}</span> left</p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-glass-100">
                            <div class="h-full rounded-full bg-crest-500 transition-all" :style="`width: ${Math.min(100, seats / {{ $program->max_capacity }} * 100)}%`" style="width: {{ $program->capacityPercent() }}%"></div>
                        </div>
                    </div>
                @endif

                <div class="p-4">
                    @if ($program->status === 'cancelled')
                        <p class="text-sm text-ink-soft">Registration is closed because this event was cancelled.</p>
                    @elseif ($registration && $registration->status === 'attended')
                        <p class="flex items-center gap-2 font-bold text-st-active"><x-icon name="check" /> You attended this event.</p>
                    @elseif ($registration && $registration->status === 'absent')
                        <p class="text-sm text-ink-soft">You were marked absent. If that's wrong, tell the MT.</p>
                    @elseif ($program->isRegistrationOpen() && ($isRegistered || ! $program->isFull()))
                        <div x-data="joinButton({ registered: @js($isRegistered), registerUrl: @js(route('programs.register', $program)), cancelUrl: @js(route('programs.cancel', $program)) })">
                            <p x-show="registered" class="mb-3 flex items-center gap-2 font-bold text-st-active"><x-icon name="check" /> You're registered</p>
                            <button type="button" @click="toggle" :disabled="loading" class="btn w-full py-3"
                                    :class="registered ? 'border border-glass-300 bg-white text-ink-soft hover:text-st-inactive' : 'bg-crest-500 text-abyss-950 hover:bg-crest-400'">
                                <span x-text="registered ? 'Cancel my registration' : 'Register for this event'">{{ $isRegistered ? 'Cancel my registration' : 'Register for this event' }}</span>
                            </button>
                            <p class="mt-2 text-center text-xs text-ink-soft">Registration closes {{ $program->registrationCloseTime()->format('j M, g:i A') }}</p>
                        </div>
                    @elseif ($isRegistered)
                        <p class="flex items-center gap-2 font-bold text-st-active"><x-icon name="check" /> You're registered</p>
                        <p class="mt-1 text-sm text-ink-soft">{{ $program->isOnline() ? 'During the session, scan the QR code the MT shares on screen to check in.' : 'On the day, scan the QR code the MT shows to check in.' }}</p>
                    @elseif ($program->isFull())
                        <p class="font-bold text-st-inactive">This event is full.</p>
                        <p class="mt-1 text-sm text-ink-soft">If a seat frees up, it'll show here.</p>
                    @else
                        <p class="text-sm text-ink-soft">Registration for this event is closed.</p>
                    @endif
                </div>
            </div>

            @can('exco')
                <div class="panel p-4 text-sm">
                    <p class="font-bold">MT view</p>
                    <p class="mt-1 text-ink-soft">{{ $program->attended_count }} attended of {{ $program->seats_taken }} registered.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('exco.attendance.qr', $program) }}" class="btn-line btn-sm"><x-icon name="qr" class="size-4" /> Check-in QR</a>
                        <a href="{{ route('exco.attendance.export', $program) }}" class="btn-line btn-sm"><x-icon name="download" class="size-4" /> CSV</a>
                        <a href="{{ route('exco.programs.report', $program) }}" class="btn-line btn-sm" data-no-prefetch><x-icon name="file" class="size-4" /> Report</a>
                    </div>
                </div>
            @endcan
        </aside>
    </div>

</x-layouts.app>
