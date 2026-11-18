@props(['title' => null])
@php
    $u = auth()->user();
    $pendingCount = $u->isExco() ? \App\Models\User::query()->pending()->count() : 0;
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'is-active' : '';
@endphp
<x-layouts.base :title="$title" body-class="bg-glass-100">
    <div x-data="{ drawer: false }" class="min-h-dvh lg:grid lg:grid-cols-[16rem_1fr] lg:bg-[linear-gradient(to_right,var(--color-abyss-900)_16rem,transparent_16rem)]">

        {{-- Sidebar (desktop) / drawer (mobile) --}}
        <div x-cloak x-show="drawer" x-transition.opacity.duration.200ms class="fixed inset-0 z-40 bg-abyss-950/60 lg:hidden" @click="drawer = false"></div>
        <aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-abyss-900 transition-transform duration-200 ease-out will-change-transform lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
               :class="drawer && 'translate-x-0'">
            <a href="{{ route('home') }}" class="flex items-center gap-3 px-5 py-5">
                <img src="{{ asset('images/ycc-crest-192.png') }}" alt="" class="size-10">
                <span>
                    <span class="block font-display text-2xl font-extrabold leading-none text-white">YCC Hub</span>
                    <span class="text-xs text-glass-300">Young Conservationist Club</span>
                </span>
            </a>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6">
                @can('exco')
                    <a href="{{ route('exco.programs.create') }}" class="btn-gold w-full"><x-icon name="plus" class="size-4" /> Create event</a>
                @endcan

                <div class="space-y-1">
                    <a href="{{ route('portal') }}" class="nav-link {{ $is('portal') }}"><x-icon name="home" /> My hub</a>
                    <a href="{{ route('programs.index') }}" class="nav-link {{ $is('programs.*', 'checkin.*') }}"><x-icon name="calendar" /> Events</a>
                    @if ($u->earnsMerit())
                        <a href="{{ route('merit.index') }}" class="nav-link {{ $is('merit.*') }}"><x-icon name="star" /> My merit</a>
                    @endif
                    <a href="{{ route('mt.index') }}" class="nav-link {{ $is('mt.*') }}"><x-icon name="shield" /> Our MT</a>
                    <a href="{{ route('profile.edit') }}" class="nav-link {{ $is('profile.*') }}"><x-icon name="user" /> My profile</a>
                    <a href="{{ route('pwa.guide') }}" class="nav-link" x-data x-show="!$store.app.standalone"><x-icon name="smartphone" /> Get the app</a>
                </div>

                @can('exco')
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-xs font-bold text-crest-500/80">MT</p>
                        <a href="{{ route('exco.dashboard') }}" class="nav-link {{ $is('exco.dashboard') }}"><x-icon name="chart" /> Dashboard</a>
                        <a href="{{ route('exco.members.index') }}" class="nav-link {{ $is('exco.members.*') }}"><x-icon name="users" /> Members</a>
                        <a href="{{ route('exco.approvals.index') }}" class="nav-link {{ $is('exco.approvals.*') }}">
                            <x-icon name="inbox" /> Approvals
                            @if ($pendingCount)
                                <span class="ml-auto rounded-full bg-crest-500 px-2 py-0.5 text-xs font-bold text-abyss-950">{{ $pendingCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('exco.programs.index') }}" class="nav-link {{ $is('exco.programs.index', 'exco.programs.edit', 'exco.attendance.*') }}"><x-icon name="qr" /> Manage events</a>
                        <a href="{{ route('exco.announcements.index') }}" class="nav-link {{ $is('exco.announcements.*') }}"><x-icon name="send" /> Email members</a>
                        <a href="{{ route('admin.analytics') }}" class="nav-link {{ $is('admin.analytics') }}"><x-icon name="pie" /> Analytics</a>
                        <a href="{{ route('admin.settings.edit') }}" class="nav-link {{ $is('admin.settings.*') }}"><x-icon name="settings" /> Settings</a>
                    </div>
                @endcan

                @if ($u->isAdvisor())
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-xs font-bold text-crest-500/80">Advisor · view-only</p>
                        <a href="{{ route('exco.dashboard') }}" class="nav-link {{ $is('exco.dashboard') }}"><x-icon name="chart" /> Dashboard</a>
                        <a href="{{ route('exco.members.index') }}" class="nav-link {{ $is('exco.members.*') }}"><x-icon name="users" /> Members</a>
                        <a href="{{ route('exco.programs.index') }}" class="nav-link {{ $is('exco.programs.index', 'exco.attendance.*') }}"><x-icon name="qr" /> Events and attendance</a>
                        <a href="{{ route('admin.analytics') }}" class="nav-link {{ $is('admin.analytics') }}"><x-icon name="pie" /> Analytics</a>
                        <a href="{{ route('exco.announcements.index') }}" class="nav-link {{ $is('exco.announcements.*') }}"><x-icon name="send" /> Emails sent</a>
                        <a href="{{ route('admin.activity') }}" class="nav-link {{ $is('admin.activity') }}"><x-icon name="clock" /> Activity log</a>
                    </div>
                @endif

                @can('superadmin')
                    <div class="space-y-1">
                        <p class="px-3 pb-1 text-xs font-bold text-crest-500/80">Superadmin</p>
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ $is('admin.users.*') }}"><x-icon name="users" /> Roles &amp; access</a>
                        <a href="{{ route('admin.activity') }}" class="nav-link {{ $is('admin.activity') }}"><x-icon name="clock" /> Activity log</a>
                    </div>
                @endcan
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3">
                    <a href="{{ route('profile.edit') }}" class="shrink-0 rounded-full" aria-label="My profile"><x-avatar :user="$u" tone="bg-crest-500 text-abyss-950" /></a>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-white">{{ $u->full_name }}</p>
                        <p class="text-xs text-glass-300">{{ $u->matric_no }} &middot; {{ $u->titleLabel() }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn-logout w-full"><x-icon name="logout" class="size-4" /> Log out</button>
                </form>
            </div>
        </aside>

        <div class="min-w-0">
            {{-- Mobile top bar --}}
            <header class="sticky top-0 z-30 flex items-center justify-between bg-abyss-900 px-4 py-2.5 lg:hidden">
                <a href="{{ route('portal') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/ycc-crest-192.png') }}" alt="" class="size-8">
                    <span class="font-display text-xl font-extrabold text-white">YCC Hub</span>
                </a>
                <button type="button" class="relative rounded-md p-2 text-glass-200" @click="drawer = true" aria-label="Open menu">
                    <x-icon name="menu" class="size-6" />
                    @if ($pendingCount)<span class="absolute right-1 top-1 size-2.5 rounded-full bg-crest-500"></span>@endif
                </button>
            </header>

            <main class="mx-auto max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-12 lg:pt-8 {{ $u->isAdvisor() && request()->routeIs('exco.*', 'admin.*') ? 'view-only' : '' }}">
                @if ($u->isAdvisor() && request()->routeIs('exco.*', 'admin.*'))
                    <p class="mb-5 flex items-center gap-2 rounded-lg border border-glass-200 bg-white px-4 py-2.5 text-sm text-ink-soft"><x-icon name="shield" class="size-4 shrink-0 text-kelp-500" /> <span><span class="font-bold text-ink">View-only.</span> As advisor you can see everything here; changes are made by the MT.</span></p>
                @endif
                @if ($u->isAdmin() && ! request()->routeIs('admin.settings.*') && \App\Support\DatabaseStatus::pending())
                    <a href="{{ route('admin.settings.edit') }}#database" class="mb-5 flex items-center gap-3 rounded-lg border border-crest-500 bg-crest-300/25 px-4 py-3 text-sm hover:bg-crest-300/40">
                        <x-icon name="alert" class="size-5 shrink-0 text-crest-700" />
                        <span class="min-w-0 flex-1"><span class="font-bold">Database update needed.</span> Go to Settings and press "Update database".</span>
                        <span class="shrink-0 font-bold text-kelp-500">Open</span>
                    </a>
                @endif
                {{ $slot }}
            </main>

            {{-- Mobile bottom tabs --}}
            @php($tabCount = 3 + ($u->isExco() ? 2 : 0) + ($u->earnsMerit() ? 1 : 0))
            <nav class="fixed inset-x-0 bottom-0 z-30 grid border-t border-glass-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden {{ ['', '', '', 'grid-cols-3', 'grid-cols-4', 'grid-cols-5', 'grid-cols-6'][$tabCount] }}">
                @php($tab = fn ($active) => $active ? 'text-abyss-900' : 'text-ink-soft')
                <a href="{{ route('portal') }}" class="flex flex-col items-center gap-0.5 py-2 text-xs transition-colors duration-150 active:bg-glass-100 {{ $tab(request()->routeIs('portal')) }}"><x-icon name="home" /> Hub</a>
                <a href="{{ route('programs.index') }}" class="flex flex-col items-center gap-0.5 py-2 text-xs transition-colors duration-150 active:bg-glass-100 {{ $tab(request()->routeIs('programs.*')) }}"><x-icon name="calendar" /> Events</a>
                @can('exco')
                    <a href="{{ route('exco.programs.create') }}" class="flex flex-col items-center gap-0.5 py-1.5 text-xs font-bold text-abyss-900 transition-colors duration-150 active:bg-glass-100" aria-label="Create event">
                        <span class="flex size-8 items-center justify-center rounded-full bg-crest-500 text-abyss-950"><x-icon name="plus" class="size-5" /></span> Create
                    </a>
                    <a href="{{ route('exco.dashboard') }}" class="flex flex-col items-center gap-0.5 py-2 text-xs transition-colors duration-150 active:bg-glass-100 {{ $tab(request()->routeIs('exco.*') && ! request()->routeIs('exco.programs.create')) }}"><x-icon name="chart" /> MT</a>
                @endcan
                @if ($u->earnsMerit())
                    <a href="{{ route('merit.index') }}" class="flex flex-col items-center gap-0.5 py-2 text-xs transition-colors duration-150 active:bg-glass-100 {{ $tab(request()->routeIs('merit.*')) }}"><x-icon name="star" /> Merit</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="flex flex-col items-center gap-0.5 py-2 text-xs transition-colors duration-150 active:bg-glass-100 {{ $tab(request()->routeIs('profile.*')) }}"><x-icon name="user" /> Profile</a>
            </nav>
        </div>
    </div>
</x-layouts.base>
