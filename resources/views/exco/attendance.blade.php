<x-layouts.app :title="'Attendance: '.$program->title">
    <x-page-header :title="$program->title" :subtitle="$program->starts_at->format('l, j M Y, g:i A').' · '.$program->whereLabel()" :back="[route('exco.programs.index'), 'Manage events']">
        <x-slot:actions>
            <a href="{{ route('exco.attendance.qr', $program) }}" class="btn-dark"><x-icon name="qr" class="size-4" /> Show check-in QR</a>
            <a href="{{ route('exco.programs.report', $program) }}" class="btn-line" data-no-prefetch><x-icon name="file" class="size-4" /> Event report</a>
            <a href="{{ route('exco.attendance.export', $program) }}" class="btn-line"><x-icon name="download" class="size-4" /> CSV</a>
        </x-slot:actions>
    </x-page-header>

    <div x-data="attendanceSheet({
            rows: @js($rows),
            counts: @js($counts),
            updateUrl: @js(route('exco.attendance.update', $program)),
            searchUrl: @js(route('exco.attendance.search', $program)),
            editable: @js($editable),
         })">

        {{-- Counts + controls --}}
        <div class="mb-5 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="panel grid grid-cols-4 divide-x divide-glass-100 text-center">
                <div class="p-3"><p class="font-display text-4xl font-extrabold leading-none text-abyss-900" x-text="counts.total"></p><p class="text-xs text-ink-soft">on the list</p></div>
                <div class="p-3"><p class="font-display text-4xl font-extrabold leading-none text-st-active" x-text="counts.attended"></p><p class="text-xs text-ink-soft">attended</p></div>
                <div class="p-3"><p class="font-display text-4xl font-extrabold leading-none text-st-inactive" x-text="counts.absent"></p><p class="text-xs text-ink-soft">absent</p></div>
                <div class="p-3"><p class="font-display text-4xl font-extrabold leading-none text-ink-soft" x-text="counts.unmarked"></p><p class="text-xs text-ink-soft">unmarked</p></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($program->status === 'published')
                    <form method="POST" action="{{ route('exco.attendance.checkin-toggle', $program) }}">
                        @csrf
                        <button class="{{ $program->checkin_open ? 'btn-line' : 'btn-gold' }}">
                            <span class="size-2 rounded-full {{ $program->checkin_open ? 'bg-st-active' : 'bg-abyss-900' }}"></span>
                            {{ $program->checkin_open ? 'Close QR check-in' : 'Open QR check-in' }}
                        </button>
                    </form>
                @endif
                @if ($program->hasStarted() && $editable)
                    <form method="POST" action="{{ route('exco.attendance.absent', $program) }}" onsubmit="return confirm('Mark everyone still unmarked as absent?')">
                        @csrf <button class="btn-line" :disabled="!counts.unmarked">Mark unmarked as absent</button>
                    </form>
                @endif
            </div>
        </div>

        @unless ($editable)
            <div class="mb-4 rounded-lg border border-st-risk/30 bg-white p-4 text-sm text-ink-soft">
                {{ auth()->user()->isAdvisor() ? 'View-only: advisors can see attendance but not change it.' : ($program->starts_at->isFuture() ? 'Attendance opens on the day of the event.' : 'Attendance is locked because the event was more than '.config('ycc.attendance_lock_days').' days ago. The superadmin can still change it.') }}
            </div>
        @endunless

        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-soft" />
                <input x-model="filter" class="field pl-9" placeholder="Find by name or matric" aria-label="Find on list">
            </div>
            <select x-model="view" class="field sm:w-44" aria-label="Show">
                <option value="all">Everyone</option>
                <option value="registered">Unmarked</option>
                <option value="attended">Attended</option>
                <option value="absent">Absent</option>
            </select>
            <button type="button" class="btn-line" x-show="editable" @click="walkIn.open = !walkIn.open"><x-icon name="plus" class="size-4" /> Add walk-in</button>
        </div>

        {{-- Walk-in search --}}
        <div x-cloak x-show="walkIn.open" x-collapse class="mb-3">
            <div class="panel p-4">
                <label for="walkin" class="field-label">Search approved members not on the list</label>
                <input id="walkin" x-model="walkIn.q" @input.debounce.300ms="search()" class="field" placeholder="Name or matric number">
                <ul class="mt-2 divide-y divide-glass-100">
                    <template x-for="m in walkIn.results" :key="m.id">
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0"><span class="block truncate font-bold" x-text="m.name"></span><span class="text-xs text-ink-soft" x-text="m.matric"></span></span>
                            <button type="button" class="btn-dark btn-sm" @click="addWalkIn(m)">Check in</button>
                        </li>
                    </template>
                </ul>
                <p x-show="walkIn.q.length >= 2 && !walkIn.loading && !walkIn.results.length" class="mt-2 text-sm text-ink-soft">No approved members match. They need an approved account first.</p>
            </div>
        </div>

        {{-- The sheet --}}
        <div class="panel divide-y divide-glass-100">
            <template x-for="row in visibleRows" :key="row.user_id">
                <div class="flex items-center gap-3 px-4 py-3" :class="busy[row.user_id] && 'opacity-60'">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold leading-snug [overflow-wrap:anywhere]" x-text="row.name"></p>
                        <p class="text-xs text-ink-soft">
                            <span x-text="row.matric"></span>
                            <template x-if="row.status === 'attended' && row.checked_in_at"><span> &middot; in at <span x-text="row.checked_in_at"></span><span x-show="row.method === 'qr'"> by QR</span></span></template>
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1.5" role="group" :aria-label="'Attendance for ' + row.name" @if (auth()->user()->isAdvisor()) data-mt-action @endif>
                        <button type="button" @click="mark(row, 'attended')" :disabled="!editable" :aria-pressed="row.status === 'attended'"
                                class="flex h-10 items-center gap-1.5 rounded-md border px-3 text-sm font-bold transition-colors"
                                :class="row.status === 'attended' ? 'border-st-active bg-st-active text-white' : 'border-glass-300 bg-white text-ink-soft hover:border-st-active hover:text-st-active'">
                            <x-icon name="check" class="size-4" /><span class="hidden sm:inline">Attended</span>
                        </button>
                        <button type="button" @click="mark(row, 'absent')" :disabled="!editable" :aria-pressed="row.status === 'absent'"
                                class="flex h-10 items-center gap-1.5 rounded-md border px-3 text-sm font-bold transition-colors"
                                :class="row.status === 'absent' ? 'border-st-inactive bg-st-inactive text-white' : 'border-glass-300 bg-white text-ink-soft hover:border-st-inactive hover:text-st-inactive'">
                            <x-icon name="x" class="size-4" /><span class="hidden sm:inline">Absent</span>
                        </button>
                    </div>
                </div>
            </template>
            <p x-show="!visibleRows.length" class="px-4 py-10 text-center text-ink-soft"
               x-text="rows.length ? 'Nobody matches this filter.' : 'No one has registered yet. Walk-ins can be added on the day.'"></p>
        </div>

        @if ($cancelled->isNotEmpty())
            <p class="mt-4 text-sm text-ink-soft">Cancelled: {{ $cancelled->map(fn ($r) => $r->user->full_name)->implode(', ') }}</p>
        @endif
    </div>
</x-layouts.app>
