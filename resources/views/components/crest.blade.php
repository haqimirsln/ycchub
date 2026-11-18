@props(['alt' => '', 'glow' => true])
{{--
    The club crest with its rings. The rings are positioned with equal insets on
    every side of the crest's square box, so they are always centred on it at any size.
    Size it with a width class, e.g. <x-crest class="w-64" />.
--}}
<div {{ $attributes->merge(['class' => 'relative aspect-square']) }}>
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute -inset-[34%] rounded-full border border-white/[0.07]"></span>
        <span class="absolute -inset-[21%] rounded-full border border-crest-500/20"></span>
        <span class="absolute -inset-[9%] rounded-full border-2 border-crest-500/40"></span>
    </div>
    <img src="{{ asset('images/ycc-crest.webp') }}" alt="{{ $alt }}" width="400" height="400" decoding="async" fetchpriority="high"
         class="relative block size-full rounded-full object-contain {{ $glow ? 'drop-shadow-[0_20px_60px_rgba(217,170,46,0.18)]' : '' }}">
</div>
