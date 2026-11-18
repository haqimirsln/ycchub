@props(['icon' => 'inbox', 'title' => null, 'bare' => false])
{{-- Empty state: says what's missing and, through the slot, what to do about it. --}}
<div {{ $attributes->merge(['class' => ($bare ? '' : 'panel ').'flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-glass-100 text-ink-soft">
        <x-icon :name="$icon" class="size-6" />
    </span>
    @if ($title)
        <p class="mt-4 font-bold text-ink">{{ $title }}</p>
    @endif
    <div class="mt-1 max-w-md text-[0.95rem] text-ink-soft">{{ $slot }}</div>
</div>
