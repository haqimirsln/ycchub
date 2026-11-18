@php($fluid = $fluid ?? false)
<a href="{{ $post->permalink }}" target="_blank" rel="noopener" @unless($tabbable) tabindex="-1" @endunless
   class="group relative block overflow-hidden rounded-lg bg-abyss-800 {{ $fluid ? 'aspect-square w-full' : 'size-56 sm:size-64' }}">
    @if ($post->imageUrl())
        <img src="{{ $post->imageUrl() }}" alt="{{ $post->shortCaption(90) ?: 'Instagram post by YCC' }}" loading="lazy"
             class="size-full object-cover transition-transform duration-500 group-hover:scale-105">
    @else
        <div class="flex size-full items-center justify-center text-glass-300"><x-icon name="instagram" class="size-10" /></div>
    @endif

    @if ($post->isVideo())
        <span class="absolute right-2 top-2 rounded-full bg-abyss-950/70 p-1.5 text-white"><x-icon name="play" class="size-3.5" /></span>
    @elseif ($post->isCarousel())
        <span class="absolute right-2 top-2 rounded-full bg-abyss-950/70 p-1.5 text-white"><x-icon name="layers" class="size-3.5" /></span>
    @endif

    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-abyss-950/95 via-abyss-950/60 to-transparent p-3 pt-10 text-left">
        <p class="text-xs text-crest-300">{{ $post->posted_at?->diffForHumans() }}</p>
        <p class="mt-0.5 line-clamp-2 text-sm text-white transition-opacity sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-visible:opacity-100">{{ $post->shortCaption(120) }}</p>
    </div>
</a>
