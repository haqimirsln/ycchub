<x-layouts.app title="My merit">
    <x-page-header title="My merit" subtitle="Points you earn for turning up, helping run events and doing well for the club." />
    @unless ($user->earnsMerit())
        <p class="-mt-3 mb-5 rounded-md bg-crest-300/30 px-4 py-3 text-sm text-ink"><span class="font-bold">MT and the superadmin don't collect merit.</span> This page shows the rules members see, so you can explain them.</p>
    @endunless

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            {{-- Totals --}}
            <section class="overflow-hidden rounded-lg bg-abyss-900 text-white">
                <div class="grid grid-cols-3 gap-px bg-white/10 text-center">
                    <div class="bg-abyss-900 px-4 py-6">
                        <p class="font-display text-6xl font-extrabold leading-none text-crest-300">{{ $summary['total'] }}</p>
                        <p class="mt-2 text-sm text-glass-300">total points</p>
                    </div>
                    <div class="bg-abyss-900 px-4 py-6">
                        <p class="font-display text-5xl font-extrabold leading-none text-green-300">+{{ $summary['merit'] }}</p>
                        <p class="mt-2 text-sm text-glass-300">merit</p>
                    </div>
                    <div class="bg-abyss-900 px-4 py-6">
                        <p class="font-display text-5xl font-extrabold leading-none {{ $summary['demerit'] ? 'text-red-300' : 'text-glass-300' }}">{{ $summary['demerit'] ? '-'.$summary['demerit'] : '0' }}</p>
                        <p class="mt-2 text-sm text-glass-300">demerit</p>
                    </div>
                </div>
            </section>

            {{-- History --}}
            <section class="panel">
                <h2 class="border-b border-glass-100 px-5 py-4 text-2xl font-extrabold text-abyss-900">History</h2>
                @if ($entries->isEmpty())
                    <x-empty icon="star" title="No points yet" bare>Join an event and check in on the day. Your first merit shows up here once attendance is marked.</x-empty>
                @else
                    <ul class="divide-y divide-glass-100">
                        @foreach ($entries as $e)
                            <li class="flex items-center gap-4 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-ink">{{ $e->reason }}</p>
                                    <p class="text-xs text-ink-soft">{{ $e->created_at->format('j M Y') }} &middot; {{ \App\Models\MeritEntry::SOURCE_LABELS[$e->source] ?? ucfirst($e->source) }}</p>
                                </div>
                                <span class="shrink-0 font-display text-2xl font-extrabold {{ $e->points < 0 ? 'text-st-inactive' : 'text-st-active' }}">{{ $e->points > 0 ? '+' : '' }}{{ $e->points }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if (method_exists($entries, 'links')) {{ $entries->links() }} @endif
                @endif
            </section>
        </div>

        {{-- How it works --}}
        <aside class="panel self-start p-5">
            <h2 class="text-2xl font-extrabold text-abyss-900">How to earn merit</h2>
            <ul class="mt-3 space-y-2.5 text-[0.95rem]">
                @if ($rules['attend'])<li class="flex justify-between gap-3"><span>Attend an event</span><span class="font-bold text-st-active">+{{ $rules['attend'] }}</span></li>@endif
                @if ($rules['break_bonus'])<li class="flex justify-between gap-3"><span>Event during a semester break</span><span class="font-bold text-st-active">+{{ $rules['break_bonus'] }} bonus</span></li>@endif
                @if ($rules['committee'])<li class="flex justify-between gap-3"><span>Serve as AJK for an event</span><span class="font-bold text-st-active">+{{ $rules['committee'] }}</span></li>@endif
                @foreach ($rules['milestones'] as $count => $bonus)
                    <li class="flex justify-between gap-3"><span>Reach {{ $count }} events attended</span><span class="font-bold text-st-active">+{{ $bonus }}</span></li>
                @endforeach
                @if ($rules['no_show'])<li class="flex justify-between gap-3"><span>Register and not turn up</span><span class="font-bold text-st-inactive">-{{ $rules['no_show'] }}</span></li>@endif
            </ul>
            <p class="mt-4 text-sm text-ink-soft">The MT can also give merit for achievements, or demerit for breaking club rules. Each one comes with a reason you can see here.</p>
        </aside>
    </div>
</x-layouts.app>
