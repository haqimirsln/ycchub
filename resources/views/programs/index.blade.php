<x-layouts.app title="Events">
    <x-page-header title="Events" subtitle="Register for what interests you. Cancel before the day if you can't make it, so someone else gets the seat.">
        @can('exco')
            <x-slot:actions>
                <a href="{{ route('exco.programs.create') }}" class="btn-dark"><x-icon name="plus" class="size-4" /> Create event</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <nav class="segmented" aria-label="When">
            @foreach (['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
                <a href="{{ route('programs.index', array_filter(['tab' => $key, 'category' => $category])) }}"
                   class="segment {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="category" class="sr-only">Category</label>
            <select id="category" name="category" class="field w-auto" onchange="this.form.submit()">
                <option value="">All categories</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($programs->isEmpty())
        <x-empty icon="calendar" :title="$tab === 'past' ? 'No past events' : 'Nothing scheduled yet'">
            {{ $tab === 'past' ? 'Events you could have joined will show up here once they are over.' : 'Check back soon, or follow YCC on Instagram for announcements.' }}
        </x-empty>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($programs as $p)
                @include('partials.program-card', ['p' => $p, 'reg' => $myRegs->get($p->id)])
            @endforeach
        </div>
        {{ $programs->links() }}
    @endif
</x-layouts.app>
