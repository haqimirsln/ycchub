@php
    $pushReady = app(\App\Services\PushService::class)->ready();
    $compact = $compact ?? false;
    $config = [
        'keyUrl' => route('push.key'),
        'subscribeUrl' => route('push.subscribe'),
        'unsubscribeUrl' => route('push.unsubscribe'),
        'testUrl' => route('push.test'),
        'devices' => $pushReady ? app(\App\Services\PushService::class)->deviceCount(auth()->user()) : 0,
    ];
@endphp
@if ($pushReady)
    @if ($compact)
        {{-- My hub: a nudge inside the installed app, until it's on or dismissed --}}
        <div x-data="pushToggle(@js($config))"
             x-cloak
             x-show="$store.app.standalone && supported && !checking && !subscribed && permission !== 'denied' && !bannerHidden"
             x-transition.opacity.duration.150ms
             class="mt-6 flex flex-col gap-3 rounded-lg border border-glass-200 bg-white p-4 sm:flex-row sm:items-center">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-crest-300/40 text-crest-700"><x-icon name="smartphone" class="size-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-bold">Get event reminders on your phone</p>
                <p class="text-sm text-ink-soft">A pop-up before events you registered for, and when a new event is posted.</p>
                <p x-show="message" x-text="message" class="mt-1 text-sm" :class="error ? 'text-st-inactive' : 'text-st-active'"></p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <button type="button" class="btn-dark btn-sm" :class="busy && 'is-busy'" :disabled="busy" @click="enable()">Turn on</button>
                <button type="button" class="rounded-md p-2 text-ink-soft hover:bg-glass-100" aria-label="Not now"
                        @click="hideBanner()"><x-icon name="x" class="size-4" /></button>
            </div>
        </div>
    @else
        {{-- My profile: the full control --}}
        <section class="panel p-5 sm:p-6" id="notifications" x-data="pushToggle(@js($config))">
            <h2 class="text-2xl font-extrabold text-abyss-900">Phone notifications</h2>
            <p class="mt-1 text-sm text-ink-soft">A pop-up on this phone before events you registered for, and when the MT posts a new event.</p>

            <div class="mt-4 space-y-3">
                <p x-show="checking" class="text-sm text-ink-soft">Checking this phone…</p>

                <template x-if="!checking && needsInstall">
                    <div class="rounded-md bg-glass-50 p-3 text-sm">
                        <p class="font-bold">On iPhone, add YCC Hub to your home screen first</p>
                        <p class="mt-1 text-ink-soft">iPhones only allow notifications for apps on the home screen. Add it, open YCC Hub from the home screen, then come back here.</p>
                        <a href="{{ route('pwa.guide') }}" class="mt-2 inline-block font-bold text-kelp-500 hover:underline">Show me how</a>
                    </div>
                </template>

                <template x-if="!checking && !needsInstall && !supported">
                    <p class="rounded-md bg-glass-50 p-3 text-sm text-ink-soft">This browser can’t show notifications. On Android, open YCC Hub in Chrome. On iPhone, add it to your home screen first.</p>
                </template>

                <template x-if="!checking && !needsInstall && supported && permission === 'denied' && !subscribed">
                    <p class="rounded-md bg-st-risk/10 p-3 text-sm text-ink">Notifications are blocked for YCC Hub on this phone. Allow them in your phone’s settings (Settings › Notifications), then reload this page.</p>
                </template>

                <template x-if="!checking && !needsInstall && supported && subscribed">
                    <div class="space-y-3">
                        <p class="flex items-center gap-2 text-sm font-bold text-st-active"><span class="size-2 rounded-full bg-st-active"></span> On for this phone</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-line btn-sm" :class="busy && 'is-busy'" :disabled="busy" @click="test()"><x-icon name="send" class="size-4" /> Send me a test</button>
                            <button type="button" class="btn-line btn-sm" :disabled="busy" @click="disable()">Turn off</button>
                        </div>
                    </div>
                </template>

                <template x-if="!checking && !needsInstall && supported && !subscribed && permission !== 'denied'">
                    <button type="button" class="btn-dark w-full" :class="busy && 'is-busy'" :disabled="busy" @click="enable()"><x-icon name="smartphone" class="size-4" /> Turn on for this phone</button>
                </template>

                <p x-show="message" x-text="message" class="text-sm" :class="error ? 'text-st-inactive' : 'text-ink-soft'"></p>
                <p x-show="devices > 1" class="text-xs text-ink-soft">On for <span x-text="devices"></span> of your phones or browsers.</p>
            </div>
        </section>
    @endif
@endif
