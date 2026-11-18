<x-layouts.app title="Exco dashboard">
    <x-page-header title="Dashboard" :subtitle="'Member activity as of '.now()->format('j M Y')">
        <x-slot:actions>
            <a href="{{ route('exco.members.export', ['status' => 'attention']) }}" class="btn-line"><x-icon name="download" class="size-4" /> Export at-risk + inactive</a>
            <a href="{{ route('exco.programs.create') }}" class="btn-dark"><x-icon name="plus" class="size-4" /> Create event</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Things that need doing --}}
    @if ($pendingCount || $needsAttendance->isNotEmpty())
        <div class="mb-6 grid gap-3 md:grid-cols-2">
            @if ($pendingCount)
                <a href="{{ route('exco.approvals.index') }}" class="flex items-center gap-4 rounded-lg border border-crest-500/50 bg-crest-300/20 p-4 hover:border-crest-600">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-crest-500 font-display text-2xl font-extrabold text-abyss-950">{{ $pendingCount }}</span>
                    <span><span class="block font-bold">{{ $pendingCount === 1 ? 'Sign-up' : 'Sign-ups' }} waiting for approval</span><span class="text-sm text-ink-soft">Review matric numbers and approve or reject.</span></span>
                </a>
            @endif
            @foreach ($needsAttendance->take(2) as $p)
                <a href="{{ route('exco.attendance.show', $p) }}" class="flex items-center gap-4 rounded-lg border border-st-risk/40 bg-white p-4 hover:border-st-risk">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-st-risk/15 text-st-risk"><x-icon name="check" /></span>
                    <span class="min-w-0"><span class="block truncate font-bold">Finish attendance: {{ $p->title }}</span><span class="text-sm text-ink-soft">{{ $p->unmarked_count }} still unmarked. Locks {{ $p->starts_at->copy()->addDays(config('ycc.attendance_lock_days'))->format('j M') }}.</span></span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Status summary --}}
    <section class="panel p-5 sm:p-6">
        <div class="grid grid-cols-2 gap-5 sm:grid-cols-5">
            <div>
                <p class="text-sm text-ink-soft">Total members</p>
                <p class="font-display text-5xl font-extrabold leading-none text-abyss-900">{{ $total }}</p>
            </div>
            @foreach (['active' => ['Active', 'text-st-active'], 'at_risk' => ['At-Risk', 'text-st-risk'], 'inactive' => ['Inactive', 'text-st-inactive'], 'new' => ['New', 'text-st-new']] as $key => [$label, $color])
                <a href="{{ route('exco.members.index', ['status' => $key]) }}" class="group">
                    <p class="text-sm text-ink-soft group-hover:text-ink">{{ $label }}</p>
                    <p class="font-display text-5xl font-extrabold leading-none {{ $color }}">{{ $distribution[$key]['count'] }}</p>
                    <p class="text-sm text-ink-soft">{{ $distribution[$key]['percent'] }}%</p>
                </a>
            @endforeach
        </div>

        @if ($total)
            <div class="mt-6 flex h-3 overflow-hidden rounded-full bg-glass-100" role="img"
                 aria-label="Active {{ $distribution['active']['percent'] }}%, at-risk {{ $distribution['at_risk']['percent'] }}%, inactive {{ $distribution['inactive']['percent'] }}%, new {{ $distribution['new']['percent'] }}%">
                <div class="bg-st-active" style="width: {{ $distribution['active']['percent'] }}%"></div>
                <div class="bg-st-risk" style="width: {{ $distribution['at_risk']['percent'] }}%"></div>
                <div class="bg-st-inactive" style="width: {{ $distribution['inactive']['percent'] }}%"></div>
                <div class="bg-st-new" style="width: {{ $distribution['new']['percent'] }}%"></div>
            </div>
            <p class="mt-3 text-sm text-ink-soft">
                Active means attended within {{ config('ycc.activity.active_days') }} days. At-risk is {{ config('ycc.activity.active_days') + 1 }} to {{ config('ycc.activity.at_risk_days') }} days.
                New members get {{ config('ycc.activity.grace_days') }} days to attend their first event. Semester breaks set in Settings don't count.
            </p>
        @endif
    </section>

    <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[1.25fr_1fr]">
        {{-- Members needing a nudge --}}
        <section class="panel">
            <div class="flex items-center justify-between gap-3 border-b border-glass-100 px-5 py-4">
                <h2 class="text-2xl font-extrabold text-abyss-900">Needs a nudge</h2>
                <a href="{{ route('exco.members.index', ['status' => 'attention']) }}" class="text-sm font-bold text-kelp-500 hover:underline">See everyone</a>
            </div>
            @if ($attention->isEmpty())
                <p class="px-5 py-8 text-center text-ink-soft">Nobody is at-risk or inactive right now.</p>
            @else
                <ul class="divide-y divide-glass-100">
                    @foreach ($attention as $m)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-avatar :user="$m" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('exco.members.show', $m) }}" class="block truncate font-bold hover:underline">{{ $m->full_name }}</a>
                                <p class="text-sm text-ink-soft">{{ $m->matric_no }} &middot; {{ $m->last_attended_on ? 'last attended '.$m->last_attended_on->format('j M Y') : 'never attended' }}</p>
                            </div>
                            <x-status-badge :status="$m->activity_status" />
                            @if ($m->whatsappNumber())
                                <a href="https://wa.me/{{ $m->whatsappNumber() }}" target="_blank" rel="noopener" class="rounded-md p-2 text-st-active hover:bg-glass-100" aria-label="WhatsApp {{ $m->full_name }}"><x-icon name="whatsapp" /></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Turnout --}}
        <section class="panel p-5">
            <h2 class="text-2xl font-extrabold text-abyss-900">Turnout, last events</h2>
            @if ($recentPrograms->isEmpty())
                <p class="py-8 text-center text-ink-soft">Turnout shows here after your first event.</p>
            @else
                <div class="mt-4 space-y-4">
                    @foreach ($recentPrograms as $p)
                        @php($rate = $p->seats_taken ? round($p->attended_count / $p->seats_taken * 100) : 0)
                        <a href="{{ route('exco.attendance.show', $p) }}" class="block group">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="truncate font-bold group-hover:underline">{{ $p->title }}</span>
                                <span class="shrink-0 text-ink-soft">{{ $p->attended_count }}/{{ $p->seats_taken }} &middot; {{ $rate }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-glass-100">
                                <div class="h-full rounded-full {{ $rate >= 70 ? 'bg-st-active' : ($rate >= 40 ? 'bg-st-risk' : 'bg-st-inactive') }}" style="width: {{ $rate }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-ink-soft">{{ $p->starts_at->format('j M Y') }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Upcoming --}}
    <section class="panel mt-6">
        <div class="flex items-center justify-between gap-3 border-b border-glass-100 px-5 py-4">
            <h2 class="text-2xl font-extrabold text-abyss-900">Upcoming</h2>
            <a href="{{ route('exco.programs.index') }}" class="text-sm font-bold text-kelp-500 hover:underline">Manage events</a>
        </div>
        @if ($upcoming->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">No upcoming events. <a href="{{ route('exco.programs.create') }}" class="font-bold text-abyss-900 underline">Create one</a>.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-clean">
                    <thead><tr><th>Date</th><th>Event</th><th>Seats</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($upcoming as $p)
                            <tr>
                                <td class="whitespace-nowrap">{{ $p->starts_at->format('D, j M') }}<span class="block text-xs text-ink-soft">{{ $p->starts_at->format('g:i A') }}</span></td>
                                <td><a href="{{ route('programs.show', $p) }}" class="font-bold hover:underline">{{ $p->title }}</a> @if ($p->status === 'draft')<x-status-badge status="draft" class="ml-1" />@endif</td>
                                <td class="whitespace-nowrap">{{ $p->seats_taken }}{{ $p->max_capacity ? ' / '.$p->max_capacity : '' }}</td>
                                <td class="text-right"><a href="{{ route('exco.attendance.show', $p) }}" class="btn-line btn-sm">Attendance</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
