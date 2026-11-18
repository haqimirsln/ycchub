@props(['title' => null, 'description' => null, 'bodyClass' => 'bg-glass-100'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csrf-refresh" content="{{ route('csrf.token') }}">
    <meta name="theme-color" content="#0a1e24">
    <title>{{ isset($title) ? $title.' | ' : '' }}YCC Hub</title>
    <meta name="description" content="{{ $description ?? 'Young Conservationist Club, Universiti Malaysia Terengganu. Join events, track your participation and stay in the loop.' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/app/apple-touch-icon.png') }}">
    {{-- Installable app (Add to Home Screen) --}}
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="ycc-sw" content="{{ route('pwa.sw') }}" data-scope="{{ rtrim(url('/'), '/') }}/">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="YCC Hub">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="application-name" content="YCC Hub">
    <meta property="og:title" content="{{ $title ?? 'YCC Hub' }}">
    <meta property="og:image" content="{{ asset('images/ycc-crest.png') }}">
    <link rel="preload" as="image" href="{{ asset('images/ycc-crest-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php $appBase = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/'); @endphp
    {{-- Start loading a page as soon as someone hovers a link (desktop) or touches it (phone),
         so most clicks open instantly. Downloads, exports, logout and the installer are never prefetched. --}}
    <script type="speculationrules">
    {!! json_encode(['prefetch' => [[
        'where' => ['and' => [
            ['href_matches' => $appBase.'/*'],
            ['not' => ['href_matches' => $appBase.'/*export*']],
            ['not' => ['href_matches' => $appBase.'/install*']],
            ['not' => ['href_matches' => $appBase.'/logout*']],
            ['not' => ['selector_matches' => '[download], [target], [data-no-prefetch]']],
        ]],
        'eagerness' => 'moderate',
    ]]], JSON_UNESCAPED_SLASHES) !!}
    </script>
    @stack('head')
</head>
<body class="{{ $bodyClass }} min-h-dvh">
    <div id="nav-progress" aria-hidden="true"></div>
    {{ $slot }}

    @php
        $flash = collect([
            session('success') ? ['message' => session('success'), 'type' => 'success'] : null,
            session('status') ? ['message' => session('status'), 'type' => 'success'] : null,
            session('error') ? ['message' => session('error'), 'type' => 'error'] : null,
        ])->filter()->values();
    @endphp
    <div x-data="toasts(@js($flash))" class="pointer-events-none fixed inset-x-0 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-50 flex flex-col items-center gap-2 px-4 lg:bottom-6 lg:items-end lg:pr-6" aria-live="polite">
        <template x-for="t in items" :key="t.id">
            <div x-transition.opacity.duration.200ms
                 class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg px-4 py-3 text-sm shadow-lg"
                 :class="t.type === 'error' ? 'bg-st-inactive text-white' : 'bg-abyss-900 text-white'">
                <span class="mt-0.5 shrink-0" :class="t.type === 'error' ? 'text-white' : 'text-crest-400'">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path x-show="t.type !== 'error'" d="m4.5 12.5 5 5 10-11"/><path x-show="t.type === 'error'" d="M12 7v6M12 16.5v.01"/></svg>
                </span>
                <p class="flex-1" x-text="t.message"></p>
                <button type="button" class="shrink-0 opacity-70 hover:opacity-100" @click="dismiss(t.id)" aria-label="Dismiss">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </template>
    </div>
    @stack('scripts')
</body>
</html>
