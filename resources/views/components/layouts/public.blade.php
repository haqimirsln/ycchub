@props(['title' => null, 'description' => null])
@php($social = \App\Http\Controllers\PublicController::social())
@php($homeLink = request()->routeIs('home') ? '' : route('home'))
<x-layouts.base :title="$title" :description="$description" body-class="bg-abyss-900 text-white">
    <header x-data="{ open: false }" class="relative z-30 border-b border-white/10">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/ycc-crest-192.png') }}" alt="" class="size-10">
                <span class="leading-tight">
                    <span class="block font-display text-xl font-extrabold tracking-wide">YCC Hub</span>
                    <span class="block text-xs text-glass-300">Young Conservationist Club, UMT</span>
                </span>
            </a>
            <nav class="hidden items-center gap-6 text-[0.95rem] md:flex">
                <a href="{{ $homeLink }}#programs" class="text-glass-200 transition-colors duration-150 hover:text-crest-300">Events</a>
                <a href="{{ $homeLink }}#instagram" class="text-glass-200 transition-colors duration-150 hover:text-crest-300">Latest</a>
                <a href="{{ $homeLink }}#join" class="text-glass-200 transition-colors duration-150 hover:text-crest-300">How to join</a>
                <a href="{{ route('pwa.guide') }}" class="text-glass-200 transition-colors duration-150 hover:text-crest-300">Get the app</a>
                @auth
                    <a href="{{ route('home.redirect') }}" class="btn-gold btn-sm">Open my hub</a>
                @else
                    <a href="{{ route('login') }}" class="text-glass-200 transition-colors duration-150 hover:text-crest-300">Log in</a>
                    <a href="{{ route('register') }}" class="btn-gold btn-sm">Join YCC</a>
                @endauth
            </nav>
            <button type="button" class="rounded-md p-2 text-glass-200 md:hidden" @click="open = !open" :aria-expanded="open" aria-label="Menu">
                <x-icon name="menu" class="size-6" />
            </button>
        </div>
        <div x-cloak x-show="open" x-collapse.duration.200ms class="border-t border-white/10 md:hidden">
            <nav class="flex flex-col gap-1 px-4 py-3">
                <a href="{{ $homeLink }}#programs" class="rounded px-2 py-2 text-glass-200" @click="open=false">Events</a>
                <a href="{{ $homeLink }}#instagram" class="rounded px-2 py-2 text-glass-200" @click="open=false">Latest</a>
                <a href="{{ $homeLink }}#join" class="rounded px-2 py-2 text-glass-200" @click="open=false">How to join</a>
                <a href="{{ route('pwa.guide') }}" class="flex items-center gap-2 rounded px-2 py-2 text-glass-200"><x-icon name="smartphone" class="size-4" /> Get the app</a>
                @auth
                    <a href="{{ route('home.redirect') }}" class="btn-gold mt-2">Open my hub</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost-light mt-2">Log in</a>
                    <a href="{{ route('register') }}" class="btn-gold">Join YCC</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>{{ $slot }}</main>

    <footer class="border-t border-white/10 bg-abyss-950">
        <div class="mx-auto grid max-w-6xl grid-cols-[minmax(0,1fr)] gap-8 px-4 py-10 sm:grid-cols-[1fr_auto] sm:px-6">
            <div class="flex items-start gap-4">
                <img src="{{ asset('images/ycc-crest-192.png') }}" alt="Young Conservationist Club crest" class="size-14">
                <div class="min-w-0 text-sm text-glass-300">
                    <p class="font-display text-lg font-extrabold text-white">Young Conservationist Club</p>
                    <p>Universiti Malaysia Terengganu. Est. 2017.</p>
                    <p class="mt-2 max-w-md">Beach cleanups, hatchery trips, reef surveys and talks, run by students for Terengganu's coast.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-start gap-3">
                <a href="{{ $social['instagram_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-white/15 px-3 py-2 text-sm text-glass-200 hover:border-crest-400 hover:text-crest-300">
                    <x-icon name="instagram" class="size-4" /> {{ '@'.$social['instagram_handle'] }}
                </a>
                <a href="{{ $social['linkedin_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-white/15 px-3 py-2 text-sm text-glass-200 hover:border-crest-400 hover:text-crest-300">
                    <x-icon name="linkedin" class="size-4" /> LinkedIn
                </a>
                <a href="{{ route('pwa.guide') }}" class="inline-flex items-center gap-2 rounded-md border border-white/15 px-3 py-2 text-sm text-glass-200 hover:border-crest-400 hover:text-crest-300">
                    <x-icon name="smartphone" class="size-4" /> Get the app
                </a>
            </div>
        </div>
    </footer>
</x-layouts.base>
