@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back[0] }}" class="mb-2 inline-flex items-center gap-1 text-sm text-ink-soft hover:text-abyss-900">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 6l-6 6 6 6"/></svg>{{ $back[1] }}
            </a>
        @endif
        <h1 class="text-4xl font-extrabold text-abyss-900 sm:text-5xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1.5 text-ink-soft">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
