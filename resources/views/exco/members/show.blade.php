<x-layouts.app :title="$member->full_name">
    @php
        $photo = $member->avatarUrl();
        $joined = $member->reviewed_at ?? $member->created_at;
        $row = 'grid grid-cols-[8.5rem_minmax(0,1fr)] gap-3 px-5 py-3 sm:grid-cols-[10rem_minmax(0,1fr)]';
    @endphp

    <div class="mb-4">
        <a href="{{ route('exco.members.index') }}" class="inline-flex items-center gap-1 text-sm font-bold text-kelp-500 hover:underline">&larr; Members</a>
    </div>

    {{-- Profile card --}}
    <section class="panel mb-6 overflow-hidden" x-data="{ zoom: false }" @keydown.escape.window="zoom = false">
        <div class="h-20 bg-abyss-900 sm:h-24" aria-hidden="true"></div>
        <div class="flex flex-col gap-5 px-5 pb-6 sm:flex-row sm:items-end sm:px-7">
            <div class="-mt-14 shrink-0 sm:-mt-20">
                @if ($photo)
                    <button type="button" @click="zoom = true" class="group relative block rounded-full ring-4 ring-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-crest-500" aria-label="View {{ $member->firstName() }}'s photo full size">
                        <x-avatar :user="$member" size="3xl" />
                        <span class="absolute inset-0 flex items-center justify-center rounded-full bg-abyss-950/45 text-sm font-bold text-white opacity-0 transition-opacity group-hover:opacity-100">View photo</span>
                    </button>
                @else
                    <x-avatar :user="$member" size="3xl" class="ring-4 ring-white" />
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-4xl font-extrabold leading-none text-abyss-900 sm:text-5xl">{{ $member->full_name }}</h1>
                    @if ($member->role !== 'member')
                        <span class="rounded bg-crest-300/40 px-2 py-0.5 text-sm font-bold text-crest-700">{{ $member->titleLabel() }}</span>
                    @endif
                </div>
                <p class="mt-2 text-ink-soft">{{ $member->matric_no }}@if ($member->faculty) &middot; {{ $member->faculty }}@endif @if ($member->year_of_study) &middot; Year {{ $member->year_of_study }}@endif</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$member->activity_status" size="lg" />
                    @if ($member->account_status === 'suspended')<x-status-badge status="suspended" size="lg" />@endif
                    <span class="text-sm text-ink-soft">{{ $statusNote }}</span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0 sm:flex-col lg:flex-row">
                @if ($member->whatsappNumber())
                    <a href="https://wa.me/{{ $member->whatsappNumber() }}?text={{ urlencode('Hi '.$member->firstName().', this is YCC. ') }}" target="_blank" rel="noopener" class="btn-dark col-span-2"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                    <a href="tel:{{ $member->phone_number }}" class="btn-line"><x-icon name="phone" class="size-4" /> Call</a>
                @endif
                <a href="mailto:{{ $member->email }}" class="btn-line {{ $member->whatsappNumber() ? '' : 'col-span-2' }}"><x-icon name="inbox" class="size-4" /> Email</a>
            </div>
        </div>

        {{-- Full-size photo --}}
        @if ($photo)
            <div x-cloak x-show="zoom" x-transition.opacity.duration.150ms
                 class="fixed inset-0 z-50 flex items-center justify-center bg-abyss-950/85 p-6"
                 @click.self="zoom = false" role="dialog" aria-modal="true" aria-label="{{ $member->full_name }}'s photo">
                <figure class="relative">
                    <img src="{{ $photo }}" alt="{{ $member->full_name }}" class="w-[min(80vw,26rem)] rounded-lg shadow-2xl">
                    <figcaption class="mt-3 text-center font-bold text-white">{{ $member->full_name }}</figcaption>
                    <button type="button" @click="zoom = false" class="absolute -right-3 -top-3 flex size-10 items-center justify-center rounded-full bg-white text-abyss-900 shadow-lg" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </figure>
            </div>
        @endif
    </section>

    {{-- Numbers --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            ['Events attended', $stats['attended'], null],
            ['No-shows', $stats['absent'], $stats['absent'] >= 2 ? 'text-st-inactive' : null],
            ['Attendance rate', $stats['rate'] === null ? '-' : $stats['rate'].'%', null],
            ['Upcoming', $stats['upcoming'], null],
            ['Hours in '.now()->year, \App\Services\VolunteerHoursService::format($stats['hours']['year']), null],
        ] as [$label, $value, $tone])
            <div class="panel px-4 py-4">
                <p class="font-display text-4xl font-extrabold leading-none {{ $tone ?? 'text-abyss-900' }}">{{ $value }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-2">
        {{-- Contact and studies --}}
        <section class="panel">
            <h2 class="border-b border-glass-100 px-5 py-4 text-2xl font-extrabold text-abyss-900">Personal details</h2>
            <dl class="divide-y divide-glass-100 text-sm">
                <div class="{{ $row }}"><dt class="text-ink-soft">Full name</dt><dd class="font-bold">{{ $member->full_name }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Matric number</dt><dd class="font-bold">{{ $member->matric_no }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Phone</dt><dd class="font-bold">{{ $member->phone_number }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Email</dt><dd class="break-all font-bold">{{ $member->email }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Team</dt><dd class="font-bold">{{ $member->teamName() ?? 'Not chosen yet' }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Member type</dt><dd>{{ \App\Models\User::MEMBER_TYPES[$member->member_type ?? 'student'] ?? 'UMT student' }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Faculty</dt><dd>{{ $member->facultyName() ?? 'Not set' }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Programme</dt><dd>{{ $member->programme ?: 'Not set' }}</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Year of study</dt><dd>{{ $member->year_of_study ? 'Year '.$member->year_of_study : 'Not set' }}</dd></div>
            </dl>
        </section>

        {{-- Membership --}}
        <section class="panel self-start">
            <h2 class="border-b border-glass-100 px-5 py-4 text-2xl font-extrabold text-abyss-900">Membership</h2>
            <dl class="divide-y divide-glass-100 text-sm">
                <div class="{{ $row }}"><dt class="text-ink-soft">Role</dt><dd class="font-bold">{{ $member->roleLabel() }}</dd></div>
                @foreach ($mtHistory as $term)
                    <div class="{{ $row }}"><dt class="text-ink-soft">{{ $loop->first ? 'MT record' : '' }}</dt><dd><span class="font-bold">{{ $term->label() }}</span> <span class="text-ink-soft">Sesi {{ $term->session }}</span></dd></div>
                @endforeach
                <div class="{{ $row }}"><dt class="text-ink-soft">Account</dt><dd><x-status-badge :status="$member->account_status" /></dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Signed up</dt><dd>{{ $member->created_at->format('j M Y') }} <span class="text-ink-soft">({{ $member->created_at->diffForHumans() }})</span></dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Approved</dt><dd>{{ $member->reviewed_at?->format('j M Y') ?? '-' }}@if ($member->reviewer)<span class="block text-xs text-ink-soft">by {{ $member->reviewer->full_name }}</span>@endif</dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Last attended</dt><dd>{{ $member->last_attended_on?->format('j M Y') ?? 'Never' }}<span class="block text-xs text-ink-soft">{{ $stats['days_away'] }} counted days {{ $member->last_attended_on ? 'ago' : 'since joining' }} (semester breaks not counted)</span></dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">Volunteer hours</dt><dd>{{ \App\Services\VolunteerHoursService::format($stats['hours']['year']) }} this year<span class="text-ink-soft">, {{ \App\Services\VolunteerHoursService::format($stats['hours']['total']) }} in total</span></dd></div>
                <div class="{{ $row }}"><dt class="text-ink-soft">PDPA consent</dt><dd>{{ $member->pdpa_consent_at?->format('j M Y, g:i A') ?? 'Not recorded' }}</dd></div>
            </dl>
        </section>
    </div>

    {{-- Merit and demerit --}}
    @if ($member->earnsMerit() || $meritEntries->isNotEmpty())
        <section class="panel mt-6" x-data="{ kind: @js(old('kind', 'merit')) }">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-glass-100 px-5 py-4">
                <h2 class="text-2xl font-extrabold text-abyss-900">Merit and demerit</h2>
                <p class="text-sm"><span class="font-display text-3xl font-extrabold text-abyss-900">{{ $merit['total'] }}</span> points &middot; <span class="font-bold text-st-active">+{{ $merit['merit'] }}</span> / <span class="font-bold text-st-inactive">{{ $merit['demerit'] ? '-'.$merit['demerit'] : '0' }}</span></p>
            </div>
            <div class="grid grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="relative overflow-x-auto">
                    @if ($meritEntries->isEmpty())
                        <p class="px-5 py-8 text-center text-ink-soft">No merit yet. It's added automatically when attendance is marked.</p>
                    @else
                        <table class="table-clean min-w-[30rem]">
                            <tbody>
                                @foreach ($meritEntries as $e)
                                    <tr>
                                        <td class="whitespace-nowrap text-ink-soft">{{ $e->created_at->format('j M Y') }}</td>
                                        <td>
                                            {{ $e->reason }}
                                            <span class="block text-xs text-ink-soft">{{ \App\Models\MeritEntry::SOURCE_LABELS[$e->source] ?? $e->source }}@if ($e->giver) by {{ $e->giver->full_name }}@endif</span>
                                        </td>
                                        <td class="whitespace-nowrap text-right font-bold {{ $e->points < 0 ? 'text-st-inactive' : 'text-st-active' }}">{{ $e->points > 0 ? '+' : '' }}{{ $e->points }}</td>
                                        <td class="w-10 text-right">
                                            @if ($e->isManual())
                                                <form method="POST" action="{{ route('exco.merit.destroy', $e) }}" onsubmit="return confirm('Remove this entry?')">@csrf @method('DELETE')<button class="rounded-md p-1.5 text-ink-soft hover:text-st-inactive" aria-label="Remove entry"><x-icon name="trash" class="size-4" /></button></form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                @if ($member->earnsMerit())
                    <form method="POST" action="{{ route('exco.merit.store', $member) }}" class="space-y-4 border-t border-glass-100 p-5 lg:border-l lg:border-t-0">
                        @csrf
                        <p class="font-bold">Give merit or demerit</p>
                        <div class="segmented w-full">
                            <label class="segment flex-1 cursor-pointer text-center" :class="kind === 'merit' && 'is-active'"><input type="radio" name="kind" value="merit" x-model="kind" class="sr-only"> Merit</label>
                            <label class="segment flex-1 cursor-pointer text-center" :class="kind === 'demerit' && 'is-active'"><input type="radio" name="kind" value="demerit" x-model="kind" class="sr-only"> Demerit</label>
                        </div>
                        <div>
                            <label for="points" class="field-label">Points</label>
                            <input id="points" name="points" type="number" min="1" max="100" value="{{ old('points', 5) }}" class="field w-28" required>
                            @error('points') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="reason" class="field-label">Reason <span class="font-normal text-ink-soft">(the member sees this)</span></label>
                            <input id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" class="field" :placeholder="kind === 'merit' ? 'e.g. Best volunteer at the beach cleanup' : 'e.g. Left the event early without telling the AJK'" required>
                            @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <button class="w-full" :class="kind === 'merit' ? 'btn-dark' : 'btn-danger'" x-text="kind === 'merit' ? 'Give merit' : 'Give demerit'">Give merit</button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    {{-- Event history --}}
    <section class="panel mt-6 overflow-x-auto">
        <h2 class="border-b border-glass-100 px-5 py-4 text-2xl font-extrabold text-abyss-900">Event history</h2>
        @if ($history->isEmpty())
            <x-empty icon="calendar" title="No registrations yet" bare>Events {{ $member->firstName() }} signs up for will show up here.</x-empty>
        @else
            <table class="table-clean min-w-[36rem]">
                <thead><tr><th>Date</th><th>Event</th><th>Status</th><th>Check-in</th></tr></thead>
                <tbody>
                    @foreach ($history as $r)
                        <tr>
                            <td class="whitespace-nowrap text-ink-soft">{{ $r->program?->starts_at->format('j M Y') }}</td>
                            <td class="font-bold">{{ $r->program?->title }}@if ($r->program?->trashed())<span class="block text-xs font-normal text-ink-soft">Event deleted</span>@endif</td>
                            <td><x-status-badge :status="$r->status" /></td>
                            <td class="text-ink-soft">{{ $r->checked_in_at ? $r->checked_in_at->format('g:i A').' via '.strtoupper($r->checkin_method ?? 'manual') : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
