<x-layouts.app title="Approvals">
    <x-page-header title="Approvals" subtitle="Check the matric number and name match a real UMT student before approving." />

    <nav class="segmented mb-5" aria-label="Sign-up list">
        <a href="{{ route('exco.approvals.index') }}" class="segment {{ $tab === 'pending' ? 'is-active' : '' }}">Waiting ({{ $pendingCount }})</a>
        <a href="{{ route('exco.approvals.index', ['tab' => 'rejected']) }}" class="segment {{ $tab === 'rejected' ? 'is-active' : '' }}">Rejected</a>
    </nav>

    @if ($users->isEmpty())
        <x-empty :icon="$tab === 'pending' ? 'check' : 'inbox'" :title="$tab === 'pending' ? 'All caught up' : 'No rejected sign-ups'">
            {{ $tab === 'pending' ? 'New sign-ups appear here as soon as students register.' : 'Anyone you reject shows up here with the reason you gave.' }}
        </x-empty>
    @else
        <div x-data="{ selected: [], all: @js($users->pluck('id')), rejecting: null }">
            @if ($tab === 'pending')
                <form method="POST" action="{{ route('exco.approvals.bulk') }}" class="mb-3 flex flex-wrap items-center gap-3">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" class="size-4 accent-abyss-900" @change="selected = $event.target.checked ? [...all] : []" :checked="selected.length === all.length"> Select all
                    </label>
                    <button type="submit" class="btn-dark btn-sm" :disabled="!selected.length">
                        <x-icon name="check" class="size-4" /> Approve <span x-text="selected.length ? selected.length + ' selected' : 'selected'"></span>
                    </button>
                </form>
            @endif

            <div class="panel divide-y divide-glass-100">
                @foreach ($users as $u)
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                        @if ($tab === 'pending')
                            <input type="checkbox" class="size-4 shrink-0 accent-abyss-900" data-mt-action value="{{ $u->id }}" x-model.number="selected" aria-label="Select {{ $u->full_name }}">
                        @endif
                        <span class="hidden sm:block"><x-avatar :user="$u" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">{{ $u->full_name }} <span class="ml-1 font-normal text-ink-soft">{{ $u->matric_no }}</span></p>
                            @if ($tab === 'pending' && ($mtTerm = app(\App\Services\MtService::class)->pendingTermFor($u)))
                                <p class="my-1 inline-flex rounded bg-crest-300/40 px-2 py-0.5 text-xs font-bold text-crest-700">On the MT list as {{ $mtTerm->label() }}. Approving makes them MT.</p>
                            @endif
                            <p class="text-sm text-ink-soft">{{ $u->email }} &middot; {{ $u->phone_number }}</p>
                            <p class="text-sm"><span class="font-bold text-ink">{{ $u->teamName() ?? 'No team chosen' }}</span>@if ($u->isAlumni()) <span class="ml-1 rounded bg-st-new/12 px-1.5 py-0.5 text-xs font-bold text-st-new">Alumni</span>@endif</p>
                            <p class="text-sm text-ink-soft">{{ collect([$u->facultyName(), $u->programme, $u->year_of_study ? 'Year '.$u->year_of_study : null])->filter()->implode(', ') ?: 'No study details given' }}</p>
                            <p class="text-xs text-ink-soft">
                                Signed up {{ $u->created_at->diffForHumans() }}
                                @if ($tab === 'rejected') &middot; rejected {{ $u->reviewed_at?->format('j M Y') }}@if ($u->review_note): "{{ $u->review_note }}"@endif @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <form method="POST" action="{{ route('exco.approvals.approve', $u) }}">@csrf <button class="btn-dark btn-sm"><x-icon name="check" class="size-4" /> Approve</button></form>
                            @if ($tab === 'pending')
                                <button type="button" class="btn-quiet-danger btn-sm" data-mt-action @click="rejecting = {{ $u->id }}">Reject</button>
                            @endif
                        </div>
                    </div>
                    @if ($tab === 'pending')
                        <form x-cloak x-show="rejecting === {{ $u->id }}" method="POST" action="{{ route('exco.approvals.reject', $u) }}" class="flex flex-col gap-2 bg-glass-50 p-4 sm:flex-row">
                            @csrf
                            <label for="note-{{ $u->id }}" class="sr-only">Reason</label>
                            <input id="note-{{ $u->id }}" name="note" class="field flex-1" placeholder="Reason, shown to the applicant">
                            <div class="flex gap-2">
                                <button class="btn-danger">Reject sign-up</button>
                                <button type="button" class="btn-line" @click="rejecting = null">Keep</button>
                            </div>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
        {{ $users->links() }}
    @endif
</x-layouts.app>
