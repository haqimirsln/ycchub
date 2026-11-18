<x-layouts.app title="Activity log">
    <x-page-header title="Activity log" subtitle="Who signed up, who was approved, who made or changed events, merit given and settings changed." />

    @if (! $ready)
        <div class="panel p-5 text-sm">Press <span class="font-bold">Update database</span> in Settings to start recording activity.</div>
    @else
        <form method="GET" class="toolbar mb-5">
            <div class="toolbar-grow relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-soft" />
                <input name="q" value="{{ $filters['q'] }}" class="field pl-9" placeholder="Search a name, matric or event" aria-label="Search">
            </div>
            <select name="group" class="field" aria-label="Type">
                <option value="">Everything</option>
                @foreach (\App\Models\ActivityLog::GROUPS as $key => [$label])<option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $label }}</option>@endforeach
            </select>
            <button class="btn-line w-full sm:w-auto">Filter</button>
        </form>

        <div class="panel">
            @if ($logs->isEmpty())
                <x-empty icon="clock" title="Nothing recorded yet" bare>Activity shows up here from now on.</x-empty>
            @else
                <ul class="divide-y divide-glass-100">
                    @foreach ($logs as $log)
                        @php
                            $kind = \Illuminate\Support\Str::before($log->action, '.');
                            $tone = match ($kind) {
                                'member' => 'bg-st-new/12 text-st-new',
                                'event' => 'bg-crest-300/40 text-crest-700',
                                'merit' => 'bg-st-active/12 text-st-active',
                                default => 'bg-glass-100 text-ink-soft',
                            };
                        @endphp
                        <li class="flex gap-3 px-4 py-3 sm:px-5">
                            <span class="mt-0.5 w-16 shrink-0 self-start rounded px-1.5 py-0.5 text-center text-xs font-bold {{ $tone }}">{{ ucfirst($kind) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-[0.95rem] text-ink">{{ $log->description }}</p>
                                <p class="mt-0.5 text-xs text-ink-soft">
                                    <time datetime="{{ $log->created_at?->toIso8601String() }}" title="{{ $log->created_at?->format('j M Y, g:i a') }}">{{ $log->created_at?->format('j M Y, g:i a') }}</time>
                                    &middot;
                                    @if ($log->user)
                                        @if ($log->user->isApproved())<a href="{{ route('exco.members.show', $log->user) }}" class="font-bold text-ink hover:underline">{{ $log->user->full_name }}</a>@else<span class="font-bold text-ink">{{ $log->user->full_name }}</span>@endif
                                        ({{ $log->user->titleLabel() }})
                                    @else
                                        System or deleted account
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        {{ $logs->links() }}
    @endif
</x-layouts.app>
