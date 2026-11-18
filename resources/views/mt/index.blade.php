<x-layouts.app title="Our MT">
    <x-page-header title="Our MT" :subtitle="'Majlis Tertinggi YCC, Sesi '.$session.'. Message the right person and you\'ll get an answer faster.'" />

    @if (! $ready)
        <div class="panel p-5 text-sm">The MT list switches on after the next database update.</div>
    @else
        @if ($advisor)
            <section class="mb-6 flex items-center gap-4 rounded-lg bg-abyss-900 px-5 py-4 text-white">
                @if ($advisorUser)
                    <x-avatar :user="$advisorUser" size="lg" />
                @else
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-crest-500 text-abyss-950"><x-icon name="star" class="size-6" /></span>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-crest-300">Penasihat</p>
                    <p class="font-display text-2xl font-extrabold leading-tight">{{ $advisor }}</p>
                </div>
            </section>
        @endif

        <ul class="grid grid-cols-[minmax(0,1fr)] gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($terms as $t)
                @php
                    $person = $t->user;
                    $team = $t->team();
                @endphp
                <li class="panel flex flex-col p-5">
                    <div class="flex items-center gap-4">
                        @if ($person)
                            <x-avatar :user="$person" size="xl" />
                        @else
                            <span class="flex size-16 shrink-0 items-center justify-center rounded-full bg-glass-100 text-ink-soft"><x-icon name="user" class="size-7" /></span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-kelp-500">{{ $t->label() }}</p>
                            <p class="break-words font-bold leading-snug text-ink">{{ $t->exists ? $t->displayName() : 'To be announced' }}</p>
                            @if ($team)<p class="text-sm text-ink-soft">Leads the {{ config("ycc.teams.$team") }}</p>@endif
                        </div>
                    </div>
                    <p class="mt-3 flex-1 text-sm text-ink-soft"><span class="font-bold text-ink">Ask about:</span> {{ $t->contact() }}</p>
                    <div class="mt-4">
                        @if ($person?->whatsappNumber() && ! $person->is(auth()->user()))
                            <a href="https://wa.me/{{ $person->whatsappNumber() }}" target="_blank" rel="noopener" class="btn-line btn-sm w-full text-st-active"><x-icon name="whatsapp" class="size-4" /> WhatsApp {{ \Illuminate\Support\Str::before($t->displayName(), ' ') ?: $person->firstName() }}</a>
                        @elseif ($t->exists && ! $person)
                            <p class="rounded-md bg-glass-50 px-3 py-2 text-center text-xs text-ink-soft">Not on YCC Hub yet</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($past->isNotEmpty())
            <section class="mt-10">
                <h2 class="mb-4 text-3xl font-extrabold text-abyss-900">Past MT</h2>
                <div class="space-y-3">
                    @foreach ($past as $pastSession => $pastTerms)
                        <details class="panel group">
                            <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 font-bold">
                                Sesi {{ $pastSession }}
                                <span class="text-sm font-normal text-ink-soft group-open:hidden">{{ $pastTerms->count() }} positions</span>
                            </summary>
                            <ul class="divide-y divide-glass-100 border-t border-glass-100">
                                @foreach ($pastTerms as $t)
                                    <li class="flex flex-wrap justify-between gap-x-4 px-5 py-2.5 text-sm"><span class="text-ink-soft">{{ $t->label() }}</span><span class="font-bold text-ink">{{ $t->displayName() }}</span></li>
                                @endforeach
                            </ul>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @can('superadmin')
            <section class="mt-10 grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('admin.mt.settings') }}" class="panel space-y-4 p-5 sm:p-6">
                    @csrf @method('PUT')
                    <h2 class="text-2xl font-extrabold text-abyss-900">Page settings</h2>
                    <p class="-mt-2 text-sm text-ink-soft">Only you see this. Give people positions in <a href="{{ route('admin.users.index') }}" class="font-bold text-kelp-500 hover:underline">Roles &amp; access</a>.</p>
                    <div>
                        <label for="mt_advisor" class="field-label">Penasihat (advisor)</label>
                        <input id="mt_advisor" name="mt_advisor" value="{{ old('mt_advisor', $advisor) }}" maxlength="150" class="field" placeholder="e.g. Prof. Madya Dr. ...">
                    </div>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="hidden" name="mt_public" value="0">
                        <input type="checkbox" name="mt_public" value="1" class="mt-0.5 size-4 accent-abyss-900" @checked($isPublic)>
                        <span><span class="font-bold">Show the MT on the public home page</span><span class="block text-ink-soft">Names, positions and photos only. Phone numbers are never shown publicly.</span></span>
                    </label>
                    <button class="btn-dark">Save</button>
                </form>

                <form method="POST" action="{{ route('admin.mt.session') }}" class="panel space-y-4 p-5 sm:p-6"
                      onsubmit="return confirm('Start the new session? The current MT moves to Past MT.')">
                    @csrf
                    <h2 class="text-2xl font-extrabold text-abyss-900">Handover to a new session</h2>
                    <p class="-mt-2 text-sm text-ink-soft">Do this once the new MT is chosen. Sesi {{ $session }} moves to Past MT and stays on everyone's record. Then give the new MT their positions.</p>
                    <div>
                        <label for="session" class="field-label">New session</label>
                        @php [$from, $to] = array_pad(explode('/', $session), 2, null); @endphp
                        <input id="session" name="session" value="{{ old('session', $to ? $to.'/'.((int) $to + 1) : '') }}" class="field sm:max-w-48" placeholder="2027/2028" required>
                        @error('session') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="hidden" name="demote" value="0">
                        <input type="checkbox" name="demote" value="1" class="mt-0.5 size-4 accent-abyss-900">
                        <span><span class="font-bold">Turn the {{ $session }} MT back into members</span><span class="block text-ink-soft">Leave unticked if some of them carry on. You can change roles one by one in Roles &amp; access.</span></span>
                    </label>
                    <button class="btn-line">Start new session</button>
                </form>
            </section>
        @endcan
    @endif
</x-layouts.app>
