@props(['user', 'size' => 'md', 'tone' => null])
@php
    // Same person, same colour, everywhere in the app.
    $tones = [
        'bg-crest-300/45 text-crest-700',
        'bg-kelp-500/15 text-kelp-700',
        'bg-st-new/12 text-st-new',
        'bg-abyss-700/12 text-abyss-800',
    ];
    $tone ??= $tones[$user->id % count($tones)];
    $sizing = match ($size) {
        'sm' => 'size-8 text-xs',
        'lg' => 'size-14 text-xl',
        'xl' => 'size-16 font-display text-3xl font-extrabold',
        '2xl' => 'size-28 font-display text-5xl font-extrabold',
        '3xl' => 'size-36 font-display text-6xl font-extrabold sm:size-44 sm:text-7xl',
        default => 'size-10 text-sm',
    };
    $photo = $user->avatarUrl();
@endphp
@if ($photo)
    <img src="{{ $photo }}" alt="" loading="lazy" decoding="async"
         {{ $attributes->merge(['class' => "shrink-0 select-none rounded-full bg-glass-100 object-cover $sizing"]) }}>
@else
    <span {{ $attributes->merge(['class' => "inline-flex shrink-0 select-none items-center justify-center rounded-full font-bold $tone $sizing"]) }} aria-hidden="true">{{ $user->initials() }}</span>
@endif
