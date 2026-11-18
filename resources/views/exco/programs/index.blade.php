<x-layouts.app title="Manage events">
    <x-page-header title="Manage events">
        <x-slot:actions>
            <a href="{{ route('exco.programs.create') }}" class="btn-dark"><x-icon name="plus" class="size-4" /> Create event</a>
        </x-slot:actions>
    </x-page-header>

    <div class="-mx-4 mb-5 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <nav class="segmented" aria-label="Event list">
            @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'drafts' => 'Drafts ('.$draftCount.')', 'cancelled' => 'Cancelled'] as $key => $label)
                <a href="{{ route('exco.programs.index', ['tab' => $key]) }}" class="segment {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($programs->isEmpty())
        <x-empty icon="calendar" title="Nothing here yet">
            <a href="{{ route('exco.programs.create') }}" class="font-bold text-abyss-900 underline">Create an event</a> and it will show up in this list.
        </x-empty>
    @else
        <div class="panel relative overflow-x-auto">
            <table class="table-clean min-w-[52rem]">
                <thead><tr><th>Date</th><th>Event</th><th>Registered</th><th>Attended</th><th>Rating</th><th>Check-in</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @foreach ($programs as $p)
                        <tr>
                            <td class="whitespace-nowrap">{{ $p->starts_at->format('D, j M Y') }}<span class="block text-xs text-ink-soft">{{ $p->starts_at->format('g:i A') }}</span></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($thumb = $p->posterThumbUrl())
                                        <img src="{{ $thumb }}" alt="" loading="lazy" class="h-12 w-10 shrink-0 rounded border border-glass-200 bg-abyss-900 object-contain">
                                    @else
                                        <span class="flex h-12 w-10 shrink-0 items-center justify-center rounded border border-dashed border-glass-300 text-ink-soft" title="No poster"><x-icon name="calendar" class="size-4" /></span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('programs.show', $p) }}" class="font-bold hover:underline">{{ $p->title }}</a>
                                        <span class="block text-xs text-ink-soft">{{ $p->categoryLabel() }} &middot; {{ $p->whereLabel() }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $p->seats_taken }}{{ $p->max_capacity ? ' / '.$p->max_capacity : '' }}</td>
                            <td>{{ $p->attended_count }}</td>
                            <td class="whitespace-nowrap">
                                @if ($p->feedback_count ?? 0)
                                    <a href="{{ route('programs.show', $p) }}#feedback-summary" class="inline-flex items-center gap-1 font-bold hover:underline" title="{{ $p->feedback_count }} {{ \Illuminate\Support\Str::plural('rating', $p->feedback_count) }}">
                                        <x-icon name="star" class="size-4 fill-current text-crest-500" />{{ number_format($p->feedback_avg_rating, 1) }}
                                        <span class="font-normal text-ink-soft">({{ $p->feedback_count }})</span>
                                    </a>
                                @else
                                    <span class="text-ink-soft">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($p->checkin_open)
                                    <span class="inline-flex items-center gap-1 text-sm font-bold text-st-active"><span class="size-2 rounded-full bg-st-active"></span>Open</span>
                                @else
                                    <span class="text-sm text-ink-soft">Closed</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('exco.attendance.show', $p) }}" class="btn-line btn-sm">Attendance</a>
                                    <a href="{{ route('exco.programs.report', $p) }}" class="btn-line btn-sm" data-no-prefetch aria-label="Event report for {{ $p->title }}" title="Event report"><x-icon name="file" class="size-4" /></a>
                                    <a href="{{ route('exco.programs.edit', $p) }}" class="btn-line btn-sm" aria-label="Edit {{ $p->title }}"><x-icon name="edit" class="size-4" /></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $programs->links() }}
    @endif
</x-layouts.app>
