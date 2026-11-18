<x-layouts.app title="Analytics">
    @php
        $statusMeta = [
            'active' => ['Active', 'text-st-active', 'bg-st-active', 'Attended within '.$rules['active_days'].' days'],
            'at_risk' => ['At-Risk', 'text-st-risk', 'bg-st-risk', ($rules['active_days'] + 1).' to '.$rules['at_risk_days'].' days since last event'],
            'inactive' => ['Inactive', 'text-st-inactive', 'bg-st-inactive', 'Over '.$rules['at_risk_days'].' days, or never attended after the grace period'],
            'new' => ['New', 'text-st-new', 'bg-st-new', 'Joined in the last '.$rules['grace_days'].' days, no event yet'],
        ];
        $exportBase = array_filter(['faculty' => $filters['faculty'], 'team' => $filters['team'], 'year' => $filters['year'], 'role' => $filters['role']]);
        $maxTrend = max(1, $trend->max('attendances'));
        $hasFilter = (bool) array_filter($filters);
    @endphp

    <x-page-header title="Analytics" subtitle="Every club member by activity status, with where they come from and how attendance is trending." />

    <div x-data>

    {{-- Filters --}}
    <form method="GET" class="panel mb-6 grid grid-cols-[minmax(0,1fr)] gap-3 p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem_8rem_auto] lg:items-end">
        <div>
            <label for="f-faculty" class="field-label">Faculty</label>
            <select id="f-faculty" name="faculty" class="field">
                <option value="">All faculties</option>
                <x-faculty-options :selected="$filters['faculty']" with-none />
            </select>
        </div>
        <div>
            <label for="f-team" class="field-label">Team</label>
            <select id="f-team" name="team" class="field">
                <option value="">All teams</option>
                <x-team-options :selected="$filters['team']" />
            </select>
        </div>
        <div>
            <label for="f-year" class="field-label">Year</label>
            <select id="f-year" name="year" class="field">
                <option value="">All years</option>
                @for ($y = 1; $y <= 5; $y++)<option value="{{ $y }}" @selected($filters['year'] === (string) $y)>Year {{ $y }}</option>@endfor
                <option value="none" @selected($filters['year'] === 'none')>Not set</option>
            </select>
        </div>
        <div>
            <label for="f-role" class="field-label">Role</label>
            <select id="f-role" name="role" class="field">
                <option value="">Everyone</option>
                <option value="member" @selected($filters['role'] === 'member')>Members</option>
                <option value="admin" @selected($filters['role'] === 'admin')>MT</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn-dark">Apply</button>
            @if ($hasFilter)<a href="{{ route('admin.analytics') }}" class="btn-line">Clear</a>@endif
        </div>
    </form>

    {{-- Summary --}}
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="panel p-4">
            <p class="text-sm text-ink-soft">Members{{ $hasFilter ? ' (filtered)' : '' }}</p>
            <p class="font-display text-5xl font-extrabold leading-none text-abyss-900">{{ $total }}</p>
        </div>
        @foreach ($statusMeta as $key => [$label, $text])
            <a href="#status-lists" @click="$dispatch('show-status', '{{ $key }}')" class="panel p-4 hover:border-abyss-600">
                <p class="text-sm text-ink-soft">{{ $label }}</p>
                <p class="font-display text-5xl font-extrabold leading-none {{ $text }}">{{ $byStatus[$key]->count() }}</p>
                <p class="text-sm text-ink-soft">{{ $total ? round($byStatus[$key]->count() / $total * 100) : 0 }}%</p>
            </a>
        @endforeach
        <a href="#slipping" class="panel border-crest-500/60 bg-crest-300/15 p-4 hover:border-crest-600">
            <p class="text-sm text-ink-soft">Slipping soon</p>
            <p class="font-display text-5xl font-extrabold leading-none text-crest-700">{{ $slipping->count() }}</p>
            <p class="text-sm text-ink-soft">At-risk within {{ $slipWindow }} days</p>
        </a>
    </section>

    {{-- Every member, grouped by status --}}
    <section id="status-lists" class="panel mt-6 scroll-mt-6"
             x-data="{ tab: 'at_risk' }" @show-status.window="tab = $event.detail">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-glass-100 px-4 py-4 sm:px-5">
            <h2 class="text-3xl font-extrabold text-abyss-900">Members by status</h2>
            @foreach ($statusMeta as $key => $meta)
                <a x-cloak x-show="tab === '{{ $key }}'" href="{{ route('exco.members.export', [...$exportBase, 'status' => $key]) }}" class="btn-line btn-sm">
                    <x-icon name="download" class="size-4" /> Export {{ $meta[0] }} CSV
                </a>
            @endforeach
        </div>

        <div class="overflow-x-auto border-b border-glass-100 px-4 sm:px-5">
            <div class="flex gap-1" role="tablist">
                @foreach ($statusMeta as $key => [$label, $text, $bg])
                    <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                            class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-bold"
                            :class="tab === '{{ $key }}' ? 'border-abyss-900 text-ink' : 'border-transparent text-ink-soft hover:text-ink'">
                        <span class="size-2 rounded-full {{ $bg }}"></span>{{ $label }}
                        <span class="rounded-full bg-glass-100 px-2 py-0.5 text-xs">{{ $byStatus[$key]->count() }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @foreach ($statusMeta as $key => [$label, $text, $bg, $rule])
            <div x-cloak x-show="tab === '{{ $key }}'" role="tabpanel">
                <p class="px-4 pt-3 text-sm text-ink-soft sm:px-5">{{ $rule }}. Longest gap first.</p>
                @if ($byStatus[$key]->isEmpty())
                    <p class="px-5 py-10 text-center text-ink-soft">No {{ strtolower($label) }} members{{ $hasFilter ? ' for this filter' : '' }}.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table-clean min-w-[46rem]">
                            <thead>
                                <tr>
                                    <th>Member</th><th>Faculty</th><th>Last attended</th>
                                    <th class="text-right">{{ $key === 'new' ? 'Days since joining' : 'Days away' }}</th>
                                    <th class="text-center">Attended</th><th class="text-center">No-shows</th><th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byStatus[$key] as $m)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <x-avatar :user="$m" size="sm" />
                                                <div class="min-w-0">
                                                    <a href="{{ route('exco.members.show', $m) }}" class="font-bold hover:underline">{{ $m->full_name }}</a>
                                                    @if ($m->role === 'admin')<span class="ml-1 rounded bg-crest-300/40 px-1.5 py-0.5 text-xs font-bold text-crest-700">MT</span>@endif @if ($m->isAlumni())<span class="ml-1 rounded bg-st-new/12 px-1.5 py-0.5 text-xs font-bold text-st-new">Alumni</span>@endif
                                                    <span class="block text-xs text-ink-soft">{{ $m->matric_no }}{{ $m->year_of_study ? ' · Year '.$m->year_of_study : '' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td title="{{ $m->facultyName() }}">{{ $m->faculty ?? '-' }}</td>
                                        <td class="whitespace-nowrap">{{ $m->last_attended_on?->format('j M Y') ?? 'Never' }}</td>
                                        <td class="text-right font-bold {{ $text }}">{{ $m->days_since }}</td>
                                        <td class="text-center">{{ $m->total_attended }}</td>
                                        <td class="text-center {{ $m->no_show_count >= 2 ? 'font-bold text-st-inactive' : '' }}">{{ $m->no_show_count }}</td>
                                        <td>
                                            <div class="flex items-center gap-1">
                                                @if ($m->whatsappNumber())
                                                    <a href="https://wa.me/{{ $m->whatsappNumber() }}" target="_blank" rel="noopener" class="rounded-md p-1.5 text-st-active hover:bg-glass-100" title="WhatsApp {{ $m->phone_number }}"><x-icon name="whatsapp" /></a>
                                                @endif
                                                <a href="mailto:{{ $m->email }}" class="rounded-md p-1.5 text-ink-soft hover:bg-glass-100" title="{{ $m->email }}"><x-icon name="inbox" /></a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        {{-- Slipping soon --}}
        <section id="slipping" class="panel scroll-mt-6">
            <div class="border-b border-glass-100 px-5 py-4">
                <h2 class="text-2xl font-extrabold text-abyss-900">Slipping soon</h2>
                <p class="text-sm text-ink-soft">Active now, but they turn at-risk within {{ $slipWindow }} days unless they join an event.</p>
            </div>
            @if ($slipping->isEmpty())
                <p class="px-5 py-8 text-center text-ink-soft">Nobody is about to slip.</p>
            @else
                <ul class="divide-y divide-glass-100">
                    @foreach ($slipping as $m)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('exco.members.show', $m) }}" class="font-bold hover:underline">{{ $m->full_name }}</a>
                                <p class="text-sm text-ink-soft">{{ $m->matric_no }} &middot; last attended {{ $m->last_attended_on?->format('j M') }}</p>
                            </div>
                            <span class="shrink-0 text-right text-sm"><span class="font-display text-2xl font-extrabold text-crest-700">{{ $m->days_to_risk }}</span> {{ \Illuminate\Support\Str::plural('day', $m->days_to_risk) }} left</span>
                            @if ($m->whatsappNumber())
                                <a href="https://wa.me/{{ $m->whatsappNumber() }}" target="_blank" rel="noopener" class="rounded-md p-2 text-st-active hover:bg-glass-100" aria-label="WhatsApp {{ $m->full_name }}"><x-icon name="whatsapp" /></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Monthly attendance trend --}}
        <section class="panel p-5">
            <h2 class="text-2xl font-extrabold text-abyss-900">Attendance, last 12 months</h2>
            <p class="text-sm text-ink-soft">Check-ins per month. Hover or tap a bar for details.</p>
            <div class="mt-5 flex h-48 items-end gap-1.5 sm:gap-2" role="img" aria-label="Monthly attendance for the last 12 months">
                @foreach ($trend as $m)
                    <div class="group relative flex h-full min-w-0 flex-1 flex-col items-center justify-end" tabindex="0">
                        <span class="mb-1 text-xs font-bold text-ink">{{ $m['attendances'] ?: '' }}</span>
                        <div class="w-full rounded-t {{ $loop->last ? 'bg-crest-500' : 'bg-abyss-700' }}" style="height: {{ max(2, round($m['attendances'] / $maxTrend * 100)) }}%"></div>
                        <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden w-40 {{ $loop->index < 2 ? 'left-0' : ($loop->remaining < 2 ? 'right-0' : 'left-1/2 -translate-x-1/2') }} rounded-md bg-abyss-900 p-2 text-xs text-white shadow-lg group-hover:block group-focus:block">
                            <p class="font-bold">{{ $m['full'] }}</p>
                            <p>{{ $m['attendances'] }} check-ins, {{ $m['members'] }} members, {{ $m['programs'] }} {{ \Illuminate\Support\Str::plural('event', $m['programs']) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex gap-1.5 border-t border-glass-200 pt-1 sm:gap-2">
                @foreach ($trend as $m)<span class="min-w-0 flex-1 text-center text-[0.7rem] text-ink-soft"><span class="sm:hidden">{{ substr($m['label'], 0, 1) }}</span><span class="hidden sm:inline">{{ $m['label'] }}</span></span>@endforeach
            </div>
        </section>
    </div>

    {{-- Breakdowns --}}
    <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 2xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        @foreach ([['By team', $teamRows, 'team'], ['By faculty', $facultyRows, 'faculty'], ['By year of study', $yearRows, 'year']] as [$heading, $rows, $param])
            <section class="panel">
                <div class="border-b border-glass-100 px-5 py-4">
                    <h2 class="text-2xl font-extrabold text-abyss-900">{{ $heading }}</h2>
                    <p class="text-sm text-ink-soft">"Active rate" counts members past their first {{ $rules['grace_days'] }} days only.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-clean min-w-[34rem]">
                        <thead><tr><th>{{ ['faculty' => 'Faculty', 'team' => 'Team', 'year' => 'Year'][$param] }}</th><th class="w-2/5">Status mix</th><th class="text-right">Members</th><th class="text-right">Active rate</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.analytics', array_filter([...$filters, $param => $row['key']])) }}" class="font-bold hover:underline">{{ $row['label'] }}</a>
                                        @if ($row['name'])<span class="block text-xs text-ink-soft">{{ $row['name'] }}</span>@endif
                                    </td>
                                    <td>
                                        @if ($row['total'])
                                            <div class="flex h-2.5 overflow-hidden rounded-full bg-glass-100" title="Active {{ $row['counts']['active'] }}, at-risk {{ $row['counts']['at_risk'] }}, inactive {{ $row['counts']['inactive'] }}, new {{ $row['counts']['new'] }}">
                                                @foreach ($statusMeta as $key => $meta)
                                                    <div class="{{ $meta[2] }}" style="width: {{ $row['counts'][$key] / $row['total'] * 100 }}%"></div>
                                                @endforeach
                                            </div>
                                            <p class="mt-1 text-xs text-ink-soft">{{ $row['counts']['active'] }} active, {{ $row['counts']['at_risk'] }} at-risk, {{ $row['counts']['inactive'] }} inactive, {{ $row['counts']['new'] }} new</p>
                                        @else
                                            <span class="text-xs text-ink-soft">No members yet</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $row['total'] }}</td>
                                    <td class="text-right font-bold {{ $row['active_rate'] >= 60 ? 'text-st-active' : ($row['active_rate'] >= 35 ? 'text-st-risk' : 'text-st-inactive') }}">{{ $row['total'] ? $row['active_rate'].'%' : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-2">
    {{-- Most engaged --}}
    <section class="panel">
        <div class="border-b border-glass-100 px-5 py-4">
            <h2 class="text-2xl font-extrabold text-abyss-900">Most engaged</h2>
            <p class="text-sm text-ink-soft">Top members by events attended. Good candidates for MT or event leads.</p>
        </div>
        @if ($topMembers->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">No attendance recorded yet.</p>
        @else
            <ol class="divide-y divide-glass-100">
                @foreach ($topMembers as $m)
                    <li class="flex items-center gap-4 px-5 py-3">
                        <span class="w-6 shrink-0 text-right font-display text-2xl font-extrabold text-ink-soft">{{ $loop->iteration }}</span>
                        <x-avatar :user="$m" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('exco.members.show', $m) }}" class="font-bold hover:underline">{{ $m->full_name }}</a>
                            <p class="text-sm text-ink-soft">{{ $m->matric_no }}{{ $m->teamName() ? ' · '.$m->teamName() : '' }}</p>
                        </div>
                        <span class="shrink-0 text-sm"><span class="font-display text-2xl font-extrabold text-abyss-900">{{ $m->total_attended }}</span> attended</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Volunteer hours --}}
    <section class="panel">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-glass-100 px-5 py-4">
            <div>
                <h2 class="text-2xl font-extrabold text-abyss-900">Top volunteers, {{ now()->year }}</h2>
                <p class="text-sm text-ink-soft">Hours from events attended this year.</p>
            </div>
            <p class="text-right"><span class="font-display text-3xl font-extrabold leading-none text-abyss-900">{{ \App\Services\VolunteerHoursService::format($hoursByUser->sum()) }}</span><span class="block text-xs text-ink-soft">hours given by the club</span></p>
        </div>
        @if ($topVolunteers->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">No volunteer hours yet this year. Hours count from events with an end time.</p>
        @else
            <ol class="divide-y divide-glass-100">
                @foreach ($topVolunteers as $row)
                    <li class="flex items-center gap-4 px-5 py-3">
                        <span class="w-6 shrink-0 text-right font-display text-2xl font-extrabold text-ink-soft">{{ $loop->iteration }}</span>
                        <x-avatar :user="$row['user']" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('exco.members.show', $row['user']) }}" class="font-bold hover:underline">{{ $row['user']->full_name }}</a>
                            <p class="text-sm text-ink-soft">{{ $row['user']->matric_no }}{{ $row['user']->teamName() ? ' · '.$row['user']->teamName() : '' }}</p>
                        </div>
                        <span class="shrink-0 text-sm"><span class="font-display text-2xl font-extrabold text-abyss-900">{{ \App\Services\VolunteerHoursService::format($row['hours']) }}</span> hrs</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Merit --}}
    <section class="panel xl:col-span-2">
        <div class="border-b border-glass-100 px-5 py-4">
            <h2 class="text-2xl font-extrabold text-abyss-900">Top merit</h2>
            <p class="text-sm text-ink-soft">Members with the most merit points, after demerits. MT don't collect merit. Change the points in Settings.</p>
        </div>
        @if ($topMerit->isEmpty())
            <p class="px-5 py-8 text-center text-ink-soft">No merit yet. Members earn it by attending events, joining during breaks and being AJK.</p>
        @else
            <ol class="grid grid-cols-[minmax(0,1fr)] divide-y divide-glass-100 md:grid-cols-2 md:divide-y-0">
                @foreach ($topMerit as $row)
                    <li class="flex items-center gap-4 border-glass-100 px-5 py-3 md:border-b">
                        <span class="w-6 shrink-0 text-right font-display text-2xl font-extrabold text-ink-soft">{{ $loop->iteration }}</span>
                        <x-avatar :user="$row['user']" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('exco.members.show', $row['user']) }}" class="font-bold hover:underline">{{ $row['user']->full_name }}</a>
                            <p class="truncate text-sm text-ink-soft">{{ $row['user']->matric_no }}{{ $row['user']->teamName() ? ' · '.$row['user']->teamName() : '' }}</p>
                        </div>
                        <span class="shrink-0 text-sm"><span class="font-display text-2xl font-extrabold text-st-active">+{{ $row['points'] }}</span> pts</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
    </div>
    </div>
</x-layouts.app>