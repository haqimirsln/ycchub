@props(['title' => null, 'heading' => null, 'wide' => false])
<x-layouts.base :title="$title" body-class="bg-glass-100">
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,26rem)_1fr]">
        <aside class="relative hidden overflow-hidden bg-abyss-900 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/ycc-crest-192.png') }}" alt="" class="size-11">
                <span class="font-display text-2xl font-extrabold">YCC Hub</span>
            </a>
            <div>
                <x-crest class="ml-14 w-56 xl:w-60" :glow="false" />
                <p class="mt-20 font-display text-4xl font-extrabold leading-none">Seahorse, coral, turtle, whale shark.</p>
                <p class="mt-3 text-glass-300">Everything on the crest lives off Terengganu. That's who the club works for.</p>
            </div>
            <p class="text-sm text-glass-300">Young Conservationist Club, Universiti Malaysia Terengganu</p>
        </aside>

        <main class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
            <a href="{{ route('home') }}" class="mb-6 flex items-center gap-3 lg:hidden">
                <img src="{{ asset('images/ycc-crest-192.png') }}" alt="" class="size-12">
                <span class="font-display text-2xl font-extrabold text-abyss-900">YCC Hub</span>
            </a>
            <div class="w-full {{ $wide ? 'max-w-2xl' : 'max-w-md' }}">
                @if ($heading)
                    <h1 class="text-5xl font-extrabold text-abyss-900">{{ $heading }}</h1>
                @endif
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.base>
