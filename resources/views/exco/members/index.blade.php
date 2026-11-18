<x-layouts.app title="Members">
    <x-page-header title="Members" subtitle="Approved members, sorted by who's been away longest.">
        <x-slot:actions>
            @if ($myTeam = app(\App\Services\MtService::class)->teamLedBy(auth()->user()))
                <a href="{{ route('exco.members.index', ['status' => 'all', 'team' => $myTeam]) }}" class="btn-line"><x-icon name="shield" class="size-4" /> My team</a>
            @endif
            <a href="{{ route('exco.members.export', request()->query()) }}" class="btn-dark"><x-icon name="download" class="size-4" /> Export CSV</a>
        </x-slot:actions>
    </x-page-header>

    @php $tabs = ['attention' => 'At-risk + inactive', 'all' => 'Everyone', 'active' => 'Active', 'at_risk' => 'At-Risk', 'inactive' => 'Inactive', 'new' => 'New']; @endphp
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="segmented" aria-label="Filter by status">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('exco.members.index', array_filter([...$filters, 'status' => $key])) }}"
                   class="segment {{ $filters['status'] === $key ? 'is-active' : '' }}" @if ($filters['status'] === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <form method="GET" class="toolbar mb-5">
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
        <div class="toolbar-grow relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-soft" />
            <label for="q" class="sr-only">Search</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" class="field pl-9" placeholder="Name, matric, email or phone">
        </div>
        <label for="faculty" class="sr-only">Faculty</label>
        <select id="faculty" name="faculty" class="field">
            <option value="">All faculties</option>
            <x-faculty-options :selected="$filters['faculty']" />
        </select>
        <label for="team" class="sr-only">Team</label>
        <select id="team" name="team" class="field">
            <option value="">All teams</option>
            <x-team-options :selected="$filters['team']" />
        </select>
        <label for="role" class="sr-only">Role</label>
        <select id="role" name="role" class="field">
            <option value="">Any role</option>
            <option value="member" @selected($filters['role'] === 'member')>Members</option>
            <option value="admin" @selected($filters['role'] === 'admin')>MT</option>
        </select>
        <button type="submit" class="btn-line w-full sm:w-auto">Filter</button>
    </form>

    @if ($members->isEmpty())
        <x-empty icon="users" title="No members match these filters">Try another status tab, or clear the search.</x-empty>
    @else
        <div class="panel relative overflow-x-auto">
            <table class="table-clean min-w-[52rem]">
                <thead>
                    <tr><th>Member</th><th>Status</th><th>Last attended</th><th class="text-center">Attended</th><th class="text-center">No-shows</th><th class="text-center">Merit</th><th>Contact</th><th class="text-right"><span class="sr-only">Details</span></th></tr>
                </thead>
                <tbody>
                    @foreach ($members as $m)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :user="$m" />
                                    <div class="min-w-0">
                                        <a href="{{ route('exco.members.show', $m) }}" class="font-bold hover:underline">{{ $m->full_name }}</a>
                                        @if ($m->isAdmin())<span class="ml-1 rounded bg-crest-300/40 px-1.5 py-0.5 text-xs font-bold text-crest-700">{{ $m->positionLabel() ?? 'MT' }}</span>@endif @if ($m->isAlumni())<span class="ml-1 rounded bg-st-new/12 px-1.5 py-0.5 text-xs font-bold text-st-new">Alumni</span>@endif
                                        <span class="block text-xs text-ink-soft">{{ $m->matric_no }}@if ($m->team) &middot; {{ $m->teamName() }}@endif</span>
                                    </div>
                                </div>
                            </td>
                            <td><x-status-badge :status="$m->activity_status" /></td>
                            <td class="whitespace-nowrap">
                                {{ $m->last_attended_on?->format('j M Y') ?? 'Never' }}
                                @if ($m->last_attended_on)<span class="block text-xs text-ink-soft">{{ $m->last_attended_on->diffForHumans() }}</span>@endif
                            </td>
                            <td class="text-center">{{ $m->total_attended }}</td>
                            <td class="text-center {{ $m->no_show_count >= 2 ? 'font-bold text-st-inactive' : '' }}">{{ $m->no_show_count }}</td>
                            <td class="text-center">@if ($m->earnsMerit())@php $pts = $meritTotals[$m->id] ?? 0; @endphp<span class="font-bold {{ $pts < 0 ? 'text-st-inactive' : ($pts > 0 ? 'text-st-active' : 'text-ink-soft') }}">{{ $pts > 0 ? '+' : '' }}{{ $pts }}</span>@else<span class="text-ink-soft">-</span>@endif</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    @if ($m->whatsappNumber())
                                        <a href="https://wa.me/{{ $m->whatsappNumber() }}" target="_blank" rel="noopener" class="rounded-md p-1.5 text-st-active transition-colors hover:bg-glass-100" title="WhatsApp {{ $m->phone_number }}" aria-label="WhatsApp {{ $m->full_name }}"><x-icon name="whatsapp" /></a>
                                        <a href="tel:{{ $m->phone_number }}" class="rounded-md p-1.5 text-ink-soft transition-colors hover:bg-glass-100" title="Call {{ $m->phone_number }}" aria-label="Call {{ $m->full_name }}"><x-icon name="phone" /></a>
                                    @endif
                                    <a href="mailto:{{ $m->email }}" class="rounded-md p-1.5 text-ink-soft transition-colors hover:bg-glass-100" title="{{ $m->email }}" aria-label="Email {{ $m->full_name }}"><x-icon name="inbox" /></a>
                                </div>
                            </td>
                            <td class="text-right"><a href="{{ route('exco.members.show', $m) }}" class="btn-line btn-sm">Details</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $members->links() }}
    @endif
</x-layouts.app>
