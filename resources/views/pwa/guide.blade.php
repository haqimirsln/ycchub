<x-layouts.public title="Get the app" description="Add YCC Hub to your iPhone or Android home screen. It opens full screen like an app, no app store needed.">
    @php
        $appUrl = rtrim(url('/'), '/');
        $displayUrl = preg_replace('#^https?://#', '', $appUrl);
    @endphp

    <section class="mx-auto max-w-5xl px-4 pb-16 pt-10 sm:px-6 sm:pt-14"
             x-data="{ tab: $store.app.platform === 'android' ? 'android' : 'ios', copied: false,
                       copy() { navigator.clipboard?.writeText(@js($appUrl)).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">

        {{-- Intro --}}
        <div class="grid grid-cols-[minmax(0,1fr)] items-center gap-8 md:grid-cols-[minmax(0,1fr)_auto]">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-crest-400">No app store needed</p>
                <h1 class="mt-2 text-[clamp(2.5rem,8vw,4.5rem)] font-extrabold leading-[0.95]">Put YCC Hub on your home screen</h1>
                <p class="mt-4 max-w-xl text-lg text-glass-200">
                    It opens full screen like a normal app, stays logged in, and loads fast. Takes about 20 seconds.
                </p>
            </div>
            {{-- What it looks like on the home screen --}}
            <div class="hidden justify-center md:flex" aria-hidden="true">
                <div class="grid grid-cols-3 gap-5 rounded-[2rem] border border-white/10 bg-abyss-800/60 p-6">
                    @foreach (range(1, 6) as $i)
                        @if ($i === 5)
                            <div class="flex w-16 flex-col items-center gap-1.5">
                                <img src="{{ asset('images/app/apple-touch-icon.png') }}" alt="" class="size-16 rounded-[1.1rem] shadow-lg ring-2 ring-crest-500/60">
                                <span class="text-xs font-bold text-white">YCC Hub</span>
                            </div>
                        @else
                            <div class="flex w-16 flex-col items-center gap-1.5 opacity-40">
                                <span class="size-16 rounded-[1.1rem] bg-white/10"></span>
                                <span class="h-2 w-10 rounded bg-white/15"></span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Already installed --}}
        <template x-if="$store.app.standalone || $store.app.installed">
            <div class="mt-8 flex items-start gap-3 rounded-lg border border-st-active/40 bg-st-active/15 p-4">
                <x-icon name="check" class="mt-0.5 size-5 shrink-0 text-green-300" />
                <p class="text-glass-100"><span class="font-bold text-white">You're all set.</span> YCC Hub is on your home screen. Open it from there next time.</p>
            </div>
        </template>

        {{-- Platform switch --}}
        <div class="mt-10 inline-flex gap-1 rounded-md border border-white/15 bg-abyss-800/60 p-1" role="tablist" aria-label="Choose your phone">
            <button type="button" role="tab" :aria-selected="tab === 'ios'" @click="tab = 'ios'"
                    class="min-h-10 rounded px-5 text-sm font-bold transition-colors"
                    :class="tab === 'ios' ? 'bg-crest-500 text-abyss-950' : 'text-glass-200 hover:text-white'">iPhone / iPad</button>
            <button type="button" role="tab" :aria-selected="tab === 'android'" @click="tab = 'android'"
                    class="min-h-10 rounded px-5 text-sm font-bold transition-colors"
                    :class="tab === 'android' ? 'bg-crest-500 text-abyss-950' : 'text-glass-200 hover:text-white'">Android</button>
        </div>

        @php
            $ios = [
                ['icon' => 'external', 'title' => 'Open YCC Hub in Safari', 'body' => 'It has to be Safari. Chrome and other browsers on iPhone can\'t add web apps to the home screen.'],
                ['icon' => 'share', 'title' => 'Tap the Share button', 'body' => 'The square with an arrow pointing up. On iOS 26, tap the ••• button next to the address bar first, then Share.'],
                ['icon' => 'plus-square', 'title' => 'Tap "Add to Home Screen"', 'body' => 'Scroll down the list if you don\'t see it straight away. You may need to tap "View More".'],
                ['icon' => 'smartphone', 'title' => 'Keep "Open as Web App" on, then tap Add', 'body' => 'The name is already set to YCC Hub. Leave the switch on so it opens full screen without the Safari bars.'],
                ['icon' => 'check', 'title' => 'Open it from your home screen', 'body' => 'Log in once with your matric number. After that it opens straight to your hub.'],
            ];
            $android = [
                ['icon' => 'external', 'title' => 'Open YCC Hub in Chrome', 'body' => 'Samsung Internet works too, see the note below.'],
                ['icon' => 'dots', 'title' => 'Tap the ⋮ menu', 'body' => 'The three dots at the top right of Chrome. If the "Install app" button above is showing, you can just tap that instead and skip to the last step.'],
                ['icon' => 'install', 'title' => 'Tap "Install app"', 'body' => 'On some phones it says "Add to Home screen" instead. Both do the same thing.'],
                ['icon' => 'smartphone', 'title' => 'Tap Install', 'body' => 'YCC Hub appears on your home screen and in your app drawer, like any other app.'],
                ['icon' => 'check', 'title' => 'Open it and log in once', 'body' => 'After that it opens straight to your hub.'],
            ];
        @endphp

        <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            {{-- iPhone steps --}}
            <div x-show="tab === 'ios'" role="tabpanel" aria-label="iPhone steps">
                <template x-if="$store.app.iosNotSafari">
                    <div class="mb-5 rounded-lg border border-crest-500/40 bg-crest-500/10 p-4 text-sm text-glass-100">
                        <p class="font-bold text-crest-300">You're not in Safari right now.</p>
                        <p class="mt-1">Copy the link, open Safari, and paste it into the address bar.</p>
                    </div>
                </template>
                <ol class="space-y-3">
                    @foreach ($ios as $i => $step)
                        <li class="flex gap-4 rounded-lg border border-white/10 bg-abyss-800/50 p-4 sm:p-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-crest-500 font-display text-xl font-extrabold text-abyss-950">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <h2 class="font-sans text-lg font-bold leading-snug text-white">{{ $step['title'] }}</h2>
                                <p class="mt-1 text-glass-300">{{ $step['body'] }}</p>
                            </div>
                            <span class="hidden size-11 shrink-0 items-center justify-center rounded-lg border border-white/15 text-crest-300 sm:flex" aria-hidden="true"><x-icon :name="$step['icon']" class="size-6" /></span>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Android steps --}}
            <div x-cloak x-show="tab === 'android'" role="tabpanel" aria-label="Android steps">
                <template x-if="$store.app.canPrompt">
                    <div class="mb-5 flex flex-col gap-3 rounded-lg border border-crest-500/40 bg-crest-500/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-glass-100"><span class="font-bold text-white">Quickest way:</span> your browser can install it in one tap.</p>
                        <button type="button" class="btn-gold shrink-0" @click="$store.app.install()"><x-icon name="install" class="size-4" /> Install app</button>
                    </div>
                </template>
                <ol class="space-y-3">
                    @foreach ($android as $i => $step)
                        <li class="flex gap-4 rounded-lg border border-white/10 bg-abyss-800/50 p-4 sm:p-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-crest-500 font-display text-xl font-extrabold text-abyss-950">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <h2 class="font-sans text-lg font-bold leading-snug text-white">{{ $step['title'] }}</h2>
                                <p class="mt-1 text-glass-300">{{ $step['body'] }}</p>
                            </div>
                            <span class="hidden size-11 shrink-0 items-center justify-center rounded-lg border border-white/15 text-crest-300 sm:flex" aria-hidden="true"><x-icon :name="$step['icon']" class="size-6" /></span>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 text-sm text-glass-300">
                    <span class="font-bold text-glass-100">Samsung Internet:</span> tap the ≡ menu at the bottom right, then "Add page to", then "Home screen".
                </p>
            </div>

            {{-- Side card: the link to open --}}
            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-lg border border-white/10 bg-abyss-800/50 p-5">
                    <p class="text-sm font-bold text-crest-300">The link to open</p>
                    <p class="mt-1 break-all font-mono text-lg text-white">{{ $displayUrl }}</p>
                    <button type="button" class="btn-ghost-light btn-sm mt-3 w-full" @click="copy()">
                        <span x-text="copied ? 'Link copied' : 'Copy link'">Copy link</span>
                    </button>
                </div>
                <div class="rounded-lg border border-white/10 bg-abyss-800/50 p-5 text-sm text-glass-300">
                    <p class="font-bold text-glass-100">Good to know</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5">
                        <li>It's the same YCC Hub as the website, so your account, events and attendance are all the same.</li>
                        <li>Updates arrive by themselves. There's nothing to download again.</li>
                        <li>You need a connection to register for events or check in.</li>
                        <li>To remove it, press and hold the icon, then Remove / Uninstall.</li>
                    </ul>
                </div>
                <div class="rounded-lg border border-white/10 bg-abyss-800/50 p-5 text-sm text-glass-300">
                    <p class="font-bold text-glass-100">On a laptop?</p>
                    <p class="mt-1">In Chrome or Edge, click the install icon at the right end of the address bar.</p>
                </div>
            </aside>
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('portal') }}" class="btn-gold">Back to my hub</a>
            @else
                <a href="{{ route('register') }}" class="btn-gold">Join YCC</a>
                <a href="{{ route('login') }}" class="btn-ghost-light">Log in</a>
            @endauth
        </div>
    </section>
</x-layouts.public>
