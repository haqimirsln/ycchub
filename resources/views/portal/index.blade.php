<x-layouts.app title="My hub">
    @php
        $status = $user->activity_status;
        $toRisk = $daysSince !== null ? $rules['active_days'] - $daysSince : null;
        $message = match ($status) {
            'active' => $toRisk > 0
                ? "You last joined an event ".($daysSince < 1 ? 'today' : "{$daysSince} ".\Illuminate\Support\Str::plural('day', $daysSince).' ago').". Join another within {$toRisk} ".\Illuminate\Support\Str::plural('day', $toRisk).' to stay active.'
                : 'You joined an event recently. Keep it up.',
            'at_risk' => "It's been {$daysSince} days since your last event. Join one soon to stay active.",
            'inactive' => $user->total_attended
                ? 'You haven\'t joined an event in a while. Register for any event below to become active again.'
                : 'You haven\'t attended an event yet. Register for one below and check in on the day.',
            default => 'Welcome to YCC! Your status becomes Active after your first event.',
        };
        $panelTone = match ($status) {
            'active' => 'border-st-active/40',
            'at_risk' => 'border-st-risk/50',
            'inactive' => 'border-st-inactive/40',
            default => 'border-st-new/40',
        };
    @endphp

    {{-- Member header --}}
    <section class="overflow-hidden rounded-lg bg-abyss-900 text-white">
        <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
            <div class="flex items-center gap-4">
                <a href="{{ route('profile.edit') }}" class="shrink-0 rounded-full ring-2 ring-crest-500/70 ring-offset-2 ring-offset-abyss-900 transition-transform hover:scale-105" aria-label="Edit your profile picture"><x-avatar :user="$user" size="xl" tone="bg-crest-500 text-abyss-950" /></a>
                <div class="min-w-0">
                    <h1 class="text-4xl font-extrabold leading-none sm:text-5xl">Hi, {{ $user->firstName() }}</h1>
                    <p class="mt-1 text-glass-300">{{ $user->matric_no }}@if ($user->team) &middot; {{ $user->teamName() }}@elseif ($user->faculty) &middot; {{ $user->facultyName() }}@endif</p>
                </div>
            </div>
            @unless ($user->isAdvisor())
            <div class="grid grid-cols-3 gap-px overflow-hidden rounded-md bg-white/10 text-center sm:min-w-96">
                <div class="bg-abyss-900 px-4 py-3">
                    <p class="font-display text-4xl font-extrabold leading-none text-crest-300">{{ $user->total_attended }}</p>
                    <p class="mt-1 text-xs text-glass-300">events attended</p>
                </div>
                <div class="bg-abyss-900 px-4 py-3">
                    <p class="font-display text-4xl font-extrabold leading-none text-crest-300">{{ $user->last_attended_on?->format('j M') ?? '-' }}</p>
                    <p class="mt-1 text-xs text-glass-300">last attended</p>
                </div>
                <div class="bg-abyss-900 px-4 py-3" title="{{ \App\Services\VolunteerHoursService::format($hours['total']) }} hours since you joined">
                    <p class="font-display text-4xl font-extrabold leading-none text-crest-300">{{ \App\Services\VolunteerHoursService::format($hours['year']) }}</p>
                    <p class="mt-1 text-xs text-glass-300">hours in {{ now()->year }}</p>
                </div>
            </div>
            @endunless
        </div>
        @if ($user->hasActivityStatus())
            {{-- Members and MT attend events, so they get an activity status. --}}
            <div class="flex flex-col gap-2 border-t-4 {{ $panelTone }} bg-white px-5 py-4 text-ink sm:flex-row sm:items-center sm:gap-4 sm:px-7">
                <x-status-badge :status="$status" size="lg" class="self-start" />
                <p class="text-[0.95rem]">{{ $message }}</p>
            </div>
        @elseif ($user->isAdvisor())
            <div class="flex flex-col gap-3 border-t-4 border-crest-500/60 bg-white px-5 py-4 text-ink sm:flex-row sm:items-center sm:gap-4 sm:px-7">
                <p class="flex-1 text-[0.95rem]"><span class="font-bold">Advisor view.</span> You can see the club's dashboard, members, events, attendance and analytics. Nothing you open here can be changed by accident.</p>
                <a href="{{ route('exco.dashboard') }}" class="btn-dark btn-sm shrink-0">Open dashboard</a>
            </div>
        @endif

        @if ($staff)
            @php
                $todo = array_filter([
                    $staff['database'] ? ['Update database', route('admin.settings.edit').'#database', 'alert', true] : null,
                    $staff['pending'] ? [$staff['pending'].' '.\Illuminate\Support\Str::plural('sign-up', $staff['pending']).' to approve', route('exco.approvals.index'), 'inbox', true] : null,
                    $staff['attendance'] ? [$staff['attendance'].' '.\Illuminate\Support\Str::plural('event', $staff['attendance']).' need attendance', route('exco.dashboard'), 'check', true] : null,
                    $staff['drafts'] ? [$staff['drafts'].' '.\Illuminate\Support\Str::plural('draft', $staff['drafts']).' not published', route('exco.programs.index', ['tab' => 'drafts']), 'edit', false] : null,
                ]);
            @endphp
            <div class="flex flex-col gap-3 bg-white px-5 py-4 text-ink sm:flex-row sm:items-center sm:gap-4 sm:px-7 {{ $user->isSuperadmin() ? 'border-t-4 border-crest-500/60' : 'border-t border-glass-100' }}">
                <span class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-crest-300/40 px-3.5 py-1.5 text-sm font-bold text-crest-700 sm:self-center">
                    <span class="size-1.5 rounded-full bg-crest-600"></span>{{ $user->isSuperadmin() ? 'Superadmin' : 'MT' }}
                </span>
                <div class="min-w-0 flex-1">
                    @if ($todo)
                        <p class="text-[0.95rem]">{{ $user->isSuperadmin() ? 'Welcome back. Here\'s what needs you today:' : 'MT to-do:' }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($todo as [$label, $url, $icon, $urgent])
                                <a href="{{ $url }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-bold transition-colors {{ $urgent ? 'border-abyss-900 bg-abyss-900 text-white hover:bg-abyss-800' : 'border-glass-300 bg-white text-ink hover:border-abyss-600' }}">
                                    <x-icon :name="$icon" class="size-4" /> {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[0.95rem]">{{ $user->isSuperadmin() ? 'Welcome back. All caught up: no sign-ups waiting and attendance is done.' : 'All caught up: no sign-ups waiting and attendance is done.' }}</p>
                    @endif
                </div>
                <a href="{{ route('exco.dashboard') }}" class="btn-line btn-sm shrink-0 self-start sm:self-center">Open dashboard</a>
            </div>
        @endif
    </section>

    {{-- Existing members who joined before teams existed --}}
    @if (! $user->team && $user->hasActivityStatus())
        <a href="{{ route('profile.edit') }}#team" class="mt-6 flex items-center gap-4 rounded-lg border-2 border-crest-500 bg-crest-300/20 p-4 transition-colors hover:bg-crest-300/30">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-crest-500 text-abyss-950"><x-icon name="users" class="size-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-bold">Pick your YCC team</span>
                <span class="block text-sm text-ink-soft">Volunteerism, Entrepreneurship, Leadership or Multimedia. It takes a few seconds in your profile.</span>
            </span>
            <span class="btn-dark btn-sm shrink-0">Choose</span>
        </a>
    @elseif ($leads)
        <a href="{{ route('exco.members.index', ['status' => 'all', 'team' => $leads['team']]) }}" class="mt-6 flex items-center gap-4 rounded-lg border border-glass-200 bg-white p-4 transition-colors hover:border-abyss-700">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-crest-500 text-abyss-950"><x-icon name="shield" class="size-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-bold">You lead the {{ config('ycc.teams.'.$leads['team']) }}</span>
                <span class="block text-sm text-ink-soft">{{ $leads['count'] }} {{ $leads['count'] === 1 ? 'member' : 'members' }} in your team. See who's active and who needs a nudge.</span>
            </span>
            <span class="btn-dark btn-sm shrink-0">My team</span>
        </a>
    @elseif ($leader && $leader->user && ! $leader->user->is($user))
        <div class="mt-6 flex items-center gap-4 rounded-lg border border-glass-200 bg-white p-4">
            <x-avatar :user="$leader->user" size="lg" />
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-bold uppercase tracking-wide text-kelp-500">Your team leader · {{ $leader->label() }}</span>
                <span class="block font-bold text-ink">{{ $leader->user->full_name }}</span>
                <span class="block text-sm text-ink-soft">Questions about the {{ $user->teamName() }}? Message {{ \Illuminate\Support\Str::before($leader->displayName(), ' ') }}.</span>
            </span>
            @if ($leader->user->whatsappNumber())
                <a href="https://wa.me/{{ $leader->user->whatsappNumber() }}" target="_blank" rel="noopener" class="btn-line btn-sm shrink-0 text-st-active"><x-icon name="whatsapp" class="size-4" /> <span class="hidden sm:inline">WhatsApp</span></a>
            @endif
        </div>
    @endif

    {{-- Volunteer hours, said plainly --}}
    @if ($hours['year'] > 0)
        <p class="mt-4 flex items-center gap-2 text-[0.95rem] text-ink-soft">
            <x-icon name="check" class="size-4 shrink-0 text-st-active" />
            <span>You've given <span class="font-bold text-ink">{{ \App\Services\VolunteerHoursService::format($hours['year']) }} {{ $hours['year'] == 1 ? 'hour' : 'hours' }}</span> to conservation this year, across {{ $hours['programs_year'] }} {{ \Illuminate\Support\Str::plural('event', $hours['programs_year']) }}. Thank you.</span>
        </p>
    @endif

    {{-- Merit and the club at a glance --}}
    <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 {{ $merit ? 'lg:grid-cols-[20rem_minmax(0,1fr)]' : '' }}">
        @if ($merit)
            <a href="{{ route('merit.index') }}" class="panel group flex flex-col justify-between gap-4 p-5 transition-colors hover:border-crest-500">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-extrabold text-abyss-900">Your merit</h2>
                    <x-icon name="star" class="size-6 fill-current text-crest-500" />
                </div>
                <p class="font-display text-7xl font-extrabold leading-none text-abyss-900">{{ $merit['total'] }}<span class="ml-2 font-sans text-base font-normal text-ink-soft">points</span></p>
                <p class="text-sm text-ink-soft"><span class="font-bold text-st-active">+{{ $merit['merit'] }} merit</span> &middot; <span class="font-bold {{ $merit['demerit'] ? 'text-st-inactive' : '' }}">{{ $merit['demerit'] ? '-'.$merit['demerit'] : '0' }} demerit</span> <span class="float-right font-bold text-kelp-500 group-hover:underline">See history</span></p>
            </a>
        @endif

        <section class="panel p-5">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 class="text-2xl font-extrabold text-abyss-900">YCC right now</h2>
                <p class="text-sm text-ink-soft"><span class="font-display text-3xl font-extrabold text-abyss-900">{{ $club['total'] }}</span> members</p>
            </div>
            @php
                $tiles = ['active' => ['Active', 'text-st-active', 'bg-st-active'], 'at_risk' => ['At-Risk', 'text-st-risk', 'bg-st-risk'], 'inactive' => ['Inactive', 'text-st-inactive', 'bg-st-inactive'], 'new' => ['New', 'text-st-new', 'bg-st-new']];
            @endphp
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($tiles as $key => [$label, $text, $bar])
                    @php $cell = $club['statuses'][$key]; @endphp
                    @if (auth()->user()->isExco())
                        <a href="{{ route('exco.members.index', ['status' => $key]) }}" class="rounded-md border border-glass-200 p-3 transition-colors hover:border-abyss-600">
                    @else
                        <div class="rounded-md border border-glass-200 p-3">
                    @endif
                        <p class="text-sm font-bold {{ $text }}">{{ $label }}</p>
                        <p class="font-display text-4xl font-extrabold leading-none text-abyss-900">{{ $cell['count'] }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-glass-100"><div class="h-full rounded-full {{ $bar }}" style="width: {{ $cell['pct'] }}%"></div></div>
                        <p class="mt-1 text-xs text-ink-soft">{{ $cell['pct'] }}%</p>
                    @if (auth()->user()->isExco())
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
            <p class="mt-3 text-sm text-ink-soft">Active means attended within {{ $rules['active_days'] }} days. At-risk is {{ $rules['active_days'] + 1 }} to {{ $rules['at_risk_days'] }} days. New members get {{ $rules['grace_days'] }} days to attend their first event. Semester breaks don't count.</p>
        </section>
    </div>

    {{-- Rate events they just attended --}}
    @if ($toRate->isNotEmpty())
        <section class="mt-6 rounded-lg border border-crest-500/50 bg-crest-300/15 p-4 sm:p-5">
            <p class="flex items-center gap-2 font-bold"><x-icon name="star" class="size-5 fill-current text-crest-500" /> How did it go?</p>
            <p class="text-sm text-ink-soft">A quick rating helps the MT plan better events. Takes 10 seconds.</p>
            <ul class="mt-3 divide-y divide-crest-500/20">
                @foreach ($toRate as $r)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="min-w-0"><span class="block truncate font-bold">{{ $r->program->title }}</span><span class="text-sm text-ink-soft">{{ $r->program->starts_at->format('j M') }}</span></span>
                        <a href="{{ route('programs.show', $r->program) }}#feedback" class="btn-dark btn-sm shrink-0">Rate it</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Phone notifications: only inside the installed app, until turned on or dismissed --}}
    @include('partials.push-toggle', ['compact' => true])

    {{-- Add to home screen: phones only, until installed or dismissed --}}
    <div x-data x-cloak
         x-show="!$store.app.standalone && !$store.app.installed && !$store.app.bannerHidden && $store.app.platform !== 'desktop'"
         x-transition.opacity.duration.150ms
         class="mt-6 flex items-center gap-4 rounded-lg border border-glass-200 bg-white p-4">
        <img src="{{ asset('images/app/apple-touch-icon.png') }}" alt="" class="size-12 shrink-0 rounded-xl">
        <div class="min-w-0 flex-1">
            <p class="font-bold">Get YCC Hub on your home screen</p>
            <p class="text-sm text-ink-soft">Opens like an app, no app store needed.</p>
        </div>
        <div class="flex shrink-0 items-center gap-1">
            <template x-if="$store.app.canPrompt">
                <button type="button" class="btn-dark btn-sm" @click="$store.app.install()">Install</button>
            </template>
            <template x-if="!$store.app.canPrompt">
                <a href="{{ route('pwa.guide') }}" class="btn-dark btn-sm">How</a>
            </template>
            <button type="button" class="rounded-md p-2 text-ink-soft hover:bg-glass-100" @click="$store.app.hideBanner()" aria-label="Hide this"><x-icon name="x" class="size-4" /></button>
        </div>
    </div>

    {{-- Upcoming --}}
    <section class="mt-10">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-3xl font-extrabold text-abyss-900">Upcoming events</h2>
            <a href="{{ route('programs.index') }}" class="text-sm font-bold text-kelp-500 underline-offset-4 hover:underline">See all</a>
        </div>

        @if ($upcoming->isEmpty())
            <div class="panel px-6 py-10 text-center text-ink-soft">
                No events are open right now. New ones show up here as soon as the MT publishes them.
            </div>
        @else
            <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 xl:grid-cols-3">
                @foreach ($upcoming as $p)
                    <div class="w-[85%] shrink-0 snap-start sm:w-auto">
                        @include('partials.program-card', ['p' => $p, 'reg' => $myRegs->get($p->id)])
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- History --}}
    <section class="mt-10">
        <h2 class="mb-4 text-3xl font-extrabold text-abyss-900">My participation history</h2>
        @if ($history->isEmpty())
            <div class="panel px-6 py-10 text-center text-ink-soft">Events you've registered for will be listed here after they happen.</div>
        @else
            <div class="panel relative overflow-x-auto">
                <table class="table-clean">
                    <thead>
                        <tr><th>Date</th><th>Event</th><th class="hidden sm:table-cell">Venue</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $r)
                            <tr>
                                <td class="whitespace-nowrap text-ink-soft">{{ $r->program->starts_at->format('j M Y') }}</td>
                                <td class="font-bold">
                                    {{ $r->program->title }}
                                    @if (! $r->program->counts_for_activity)<span class="block text-xs font-normal text-ink-soft">Doesn't count toward status</span>@endif
                                </td>
                                <td class="hidden text-ink-soft sm:table-cell">{{ $r->program->whereLabel() }}</td>
                                <td>
                                    @if ($r->status === 'registered')
                                        <span class="text-sm text-ink-soft">Not marked yet</span>
                                    @else
                                        <x-status-badge :status="$r->status" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
