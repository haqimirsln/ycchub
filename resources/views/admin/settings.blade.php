<x-layouts.app title="Settings">
    <x-page-header title="Settings">
        <x-slot:actions>
            <a href="{{ route('admin.settings.exports') }}" class="btn-line">Export log</a>
        </x-slot:actions>
    </x-page-header>

    @if ($pendingMigrations)
        <div id="database" class="mb-6 flex flex-col gap-4 rounded-lg border-2 border-crest-500 bg-crest-300/20 p-5 sm:flex-row sm:items-center">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-crest-500 text-abyss-950"><x-icon name="alert" class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-bold">The last update needs a database change</p>
                <p class="text-sm text-ink-soft">{{ count($pendingMigrations) }} {{ \Illuminate\Support\Str::plural('change', count($pendingMigrations)) }} waiting. The rest of YCC Hub stays paused for members until you apply {{ count($pendingMigrations) === 1 ? 'it' : 'them' }}. Your existing data is kept.</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.database') }}" class="shrink-0">
                @csrf <button class="btn-dark w-full sm:w-auto">Update database</button>
            </form>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Instagram --}}
        <section class="panel p-5 sm:p-6 xl:row-span-2">
            <h2 class="flex items-center gap-2 text-3xl font-extrabold text-abyss-900"><x-icon name="instagram" class="size-6" /> Instagram feed</h2>
            <p class="mt-2 text-sm text-ink-soft">
                The landing page shows the latest posts. There are two ways to feed it.
            </p>

            <div class="mt-4 rounded-md bg-glass-50 p-4 text-sm">
                @if ($instagram['configured'])
                    <p><span class="font-bold">Live sync is on.</span> Token {{ $instagram['token_hint'] }}, {{ $instagram['api_count'] }} posts cached.</p>
                    <p class="text-ink-soft">Last sync: {{ $instagram['last_sync'] ?? 'not yet' }}. Token valid until about {{ $instagram['expires_at'] ?? 'unknown' }} (refreshed weekly automatically).</p>
                @else
                    <p><span class="font-bold">Live sync is off.</span> Pinned post links below are shown instead.</p>
                @endif
                @if ($instagram['last_error'])
                    <p class="mt-2 text-st-inactive">Last error: {{ $instagram['last_error'] }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.settings.instagram') }}" class="mt-5 space-y-5">
                @csrf @method('PUT')
                <div>
                    <label for="access_token" class="field-label">Option 1: Access token (live sync)</label>
                    <input id="access_token" name="access_token" type="password" class="field font-mono text-sm" placeholder="{{ $instagram['configured'] ? 'Leave empty to keep the current token' : 'Paste long-lived token' }}" autocomplete="off">
                    <details class="field-hint">
                        <summary class="cursor-pointer text-kelp-500">How to get a token</summary>
                        <ol class="mt-2 list-decimal space-y-1 pl-5">
                            <li>Switch @young.conservationist to a Professional account (Business or Creator) in the Instagram app.</li>
                            <li>At developers.facebook.com, create an app and add the Instagram product (Instagram API with Instagram Login).</li>
                            <li>Add the club account as an Instagram tester and accept the invite in the app.</li>
                            <li>Generate a token for the account with the instagram_business_basic permission, and paste it here.</li>
                        </ol>
                    </details>
                    @if ($instagram['configured'])
                        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="clear_token" value="1" class="accent-abyss-900"> Remove token and turn off live sync</label>
                    @endif
                </div>
                <div>
                    <label for="pinned_urls" class="field-label">Option 2: Pinned post links (no token needed)</label>
                    <textarea id="pinned_urls" name="pinned_urls" rows="5" class="field font-mono text-sm" placeholder="https://www.instagram.com/p/XXXXXXXX/">{{ old('pinned_urls', $instagram['pinned']) }}</textarea>
                    <p class="field-hint">One link per line, newest first. Shown with Instagram's own embed when live sync is off.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-dark">Save Instagram settings</button>
                </div>
            </form>
            @if ($instagram['configured'])
                <form method="POST" action="{{ route('admin.settings.instagram.sync') }}" class="mt-2">@csrf <button class="btn-line">Sync now</button></form>
            @endif
        </section>

        {{-- Social links --}}
        <section class="panel p-5 sm:p-6">
            <h2 class="text-3xl font-extrabold text-abyss-900">Social links</h2>
            <form method="POST" action="{{ route('admin.settings.social') }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                <div>
                    <label for="instagram_handle" class="field-label">Instagram handle</label>
                    <input id="instagram_handle" name="instagram_handle" value="{{ old('instagram_handle', $social['instagram_handle']) }}" class="field">
                    @error('instagram_handle') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="instagram_url" class="field-label">Instagram URL</label>
                    <input id="instagram_url" name="instagram_url" type="url" value="{{ old('instagram_url', $social['instagram_url']) }}" class="field">
                    @error('instagram_url') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="linkedin_url" class="field-label">LinkedIn URL</label>
                    <input id="linkedin_url" name="linkedin_url" type="url" value="{{ old('linkedin_url', $social['linkedin_url']) }}" class="field">
                    @error('linkedin_url') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button class="btn-dark">Save links</button>
            </form>
        </section>

        {{-- Pauses --}}
        <section class="panel p-5 sm:p-6">
            <h2 class="text-3xl font-extrabold text-abyss-900">Semester breaks</h2>
            <p class="mt-2 text-sm text-ink-soft">
                Days inside these ranges don't count toward inactivity, so nobody turns at-risk over the long break.
                Current rules: active within {{ $rules['active_days'] }} days, at-risk up to {{ $rules['at_risk_days'] }}, {{ $rules['grace_days'] }}-day grace for new members.
            </p>

            @if ($pauses->isNotEmpty())
                <ul class="mt-4 divide-y divide-glass-100 rounded-md border border-glass-200">
                    @foreach ($pauses as $p)
                        <li class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                            <span><span class="font-bold">{{ $p->label }}</span><span class="block text-ink-soft">{{ $p->starts_on->format('j M Y') }} to {{ $p->ends_on->format('j M Y') }} ({{ $p->starts_on->diffInDays($p->ends_on) + 1 }} days)</span></span>
                            <form method="POST" action="{{ route('admin.settings.pauses.destroy', $p) }}" onsubmit="return confirm('Remove this break? Statuses will be recalculated.')">
                                @csrf @method('DELETE') <button class="rounded-md p-2 text-ink-soft hover:text-st-inactive" aria-label="Remove {{ $p->label }}"><x-icon name="trash" class="size-4" /></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.settings.pauses.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label for="label" class="field-label">Label</label>
                    <input id="label" name="label" class="field" placeholder="e.g. Sem 1 break 2026/27" value="{{ old('label') }}">
                    @error('label') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="starts_on" class="field-label">From</label>
                    <input id="starts_on" name="starts_on" type="date" class="field" value="{{ old('starts_on') }}">
                    @error('starts_on') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ends_on" class="field-label">To</label>
                    <input id="ends_on" name="ends_on" type="date" class="field" value="{{ old('ends_on') }}">
                    @error('ends_on') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <button class="btn-dark">Add break</button>
                </div>
            </form>
            <form method="POST" action="{{ route('admin.settings.recalculate') }}" class="mt-3">
                @csrf <button class="btn-line btn-sm">Recalculate all statuses now</button>
            </form>
        </section>

        {{-- Merit --}}
        <section class="panel p-5 sm:p-6" id="merit">
            <h2 class="text-3xl font-extrabold text-abyss-900">Merit rules</h2>
            <p class="mt-2 text-sm text-ink-soft">Points normal members earn automatically. MT and the superadmin don't collect merit. Changing these recalculates everyone; merit or demerit given by hand stays as it is.</p>
            @if (! $meritReady)
                <p class="mt-3 rounded-md bg-st-at-risk/12 px-3 py-2 text-sm font-bold text-st-at-risk">Press Update database first to turn merit on.</p>
            @endif
            <form method="POST" action="{{ route('admin.settings.merit') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf @method('PUT')
                @foreach ([
                    'attend' => ['Attending an event', 'Merit per event attended'],
                    'break_bonus' => ['Semester break bonus', 'Extra merit for events during a semester break above'],
                    'committee' => ['Being AJK', 'Merit for being in an event\'s committee'],
                    'no_show' => ['No-show demerit', 'Demerit for registering and not turning up (0 = off)'],
                ] as $key => [$label, $hint])
                    <div>
                        <label for="merit_{{ $key }}" class="field-label">{{ $label }}</label>
                        <input id="merit_{{ $key }}" name="{{ $key }}" type="number" min="0" max="100" class="field" value="{{ old($key, $meritRules[$key]) }}">
                        <p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p>
                        @error($key) <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label for="merit_milestones" class="field-label">Attendance milestones</label>
                    <input id="merit_milestones" name="milestones" class="field" placeholder="5:10, 10:20, 20:30"
                        value="{{ old('milestones', collect($meritRules['milestones'])->map(fn ($b, $c) => "$c:$b")->implode(', ')) }}">
                    <p class="mt-1 text-xs text-ink-soft">Events attended : bonus points. "5:10" means +10 when a member reaches 5 events.</p>
                    @error('milestones') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2"><button class="btn-dark" @disabled(! $meritReady)>Save merit rules</button></div>
            </form>
        </section>

        {{-- Email --}}
        <section class="panel p-5 sm:p-6">
            <h2 class="text-3xl font-extrabold text-abyss-900">Email</h2>
            <p class="mt-2 text-sm text-ink-soft">Approval, rejection and password reset emails go out from <span class="font-bold text-ink">{{ config('mail.from.address') }}</span>. The mail server details live in the .env file.</p>
            <form method="POST" action="{{ route('admin.settings.test-mail') }}" class="mt-4">
                @csrf <button class="btn-line btn-sm"><x-icon name="inbox" class="size-4" /> Send a test email to me</button>
            </form>
            <p class="mt-2 text-xs text-ink-soft">Sends you the welcome email new members get, to {{ auth()->user()->email }}.</p>
            @if ($reminders['ready'])
                <form method="POST" action="{{ route('admin.settings.reminders') }}" class="mt-5 border-t border-glass-100 pt-5">
                    @csrf @method('PUT')
                    <h3 class="font-sans text-base font-bold text-ink">Event reminders</h3>
                    <p class="mt-1 text-sm text-ink-soft">Members registered for an event get an email before it starts, with the time, place and a cancel link.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input type="hidden" name="event_reminders" value="0">
                            <input type="checkbox" name="event_reminders" value="1" class="size-4 accent-abyss-900" @checked($reminders['enabled'])> Send reminders
                        </label>
                        <select name="event_reminder_hours" class="field field-sm w-auto" aria-label="How long before">
                            @foreach (\App\Services\EventReminderService::HOUR_CHOICES as $h)<option value="{{ $h }}" @selected($reminders['hours'] === $h)>{{ $h }} hours before</option>@endforeach
                        </select>
                        <button class="btn-line btn-sm">Save</button>
                    </div>
                    <p class="mt-2 text-xs text-ink-soft">
                        @if ($reminders['last'])
                            {{ number_format($reminders['total']) }} sent so far, last on {{ \Illuminate\Support\Carbon::parse($reminders['last'])->format('j M, g:i a') }}.
                        @else
                            None sent yet. Needs the cron job to be running.
                        @endif
                    </p>
                    @if ($reminders['error'])<p class="mt-1 break-words text-xs text-st-inactive">Last failure: {{ $reminders['error'] }}</p>@endif
                </form>
            @endif
            @php $push = app(\App\Services\PushService::class); @endphp
            @if ($push->ready())
                @php $pushStats = $push->stats(); $pushError = \App\Models\Setting::get('push_last_error'); @endphp
                <div class="mt-5 border-t border-glass-100 pt-5">
                    <h3 class="font-sans text-base font-bold text-ink">Phone notifications</h3>
                    <p class="mt-1 text-sm text-ink-soft">Members who turn them on (My profile › Phone notifications) also get the event reminder above as a pop-up, plus a “New event” pop-up when you publish one with the box ticked.</p>
                    <p class="mt-2 text-xs text-ink-soft">
                        On for {{ $pushStats['members'] }} {{ $pushStats['members'] === 1 ? 'member' : 'members' }} ({{ $pushStats['devices'] }} {{ $pushStats['devices'] === 1 ? 'device' : 'devices' }}).
                        @if ($pushStats['last']) Last sent {{ \Illuminate\Support\Carbon::parse($pushStats['last'])->format('j M, g:i a') }}. @else None sent yet. @endif
                        @php $pushWaiting = $push->waiting(); @endphp
                        @if ($pushWaiting) <span class="font-bold text-st-at-risk">{{ $pushWaiting }} waiting to send.</span> @endif
                    </p>
                    @if ($pushError)<p class="mt-1 break-words text-xs text-st-inactive">Last failure: {{ $pushError }}</p>@endif
                </div>
            @elseif (! \App\Support\WebPush::supported())
                <p class="mt-5 border-t border-glass-100 pt-5 text-xs text-st-risk">Phone notifications need PHP’s OpenSSL extension with elliptic-curve support, which this server doesn’t have.</p>
            @endif
            <p class="mt-4 text-sm text-ink-soft">To email members about an event, news or a reminder, use <a href="{{ route('exco.announcements.index') }}" class="font-bold text-kelp-500 hover:underline">Email members</a>.</p>
        </section>

        {{-- System --}}
        <section class="panel p-5 sm:p-6">
            <h2 class="text-3xl font-extrabold text-abyss-900">Database</h2>
            @if ($pendingMigrations)
                <p class="mt-2 text-sm text-ink-soft">{{ count($pendingMigrations) }} {{ \Illuminate\Support\Str::plural('change', count($pendingMigrations)) }} from the last update {{ count($pendingMigrations) === 1 ? 'is' : 'are' }} waiting to be applied.</p>
            @else
                <p class="mt-2 flex items-center gap-2 text-sm font-bold text-st-active"><x-icon name="check" class="size-4" /> Up to date</p>
            @endif
            <p class="mt-2 text-sm text-ink-soft">After uploading an update, press this if the update notes say so. It only adds what's new and never deletes members or events. Pressing it when nothing is waiting does nothing.</p>
            <form method="POST" action="{{ route('admin.settings.database') }}" class="mt-4">
                @csrf <button class="btn-line btn-sm">Update database</button>
            </form>
        </section>

        {{-- Cron job --}}
        @php
            $cronLast = \App\Support\Scheduler::lastRun();
            $cronOk = \App\Support\Scheduler::cronRunning();
            $cronError = \App\Support\Scheduler::lastError();
            $jobs = ['ycc:send-push' => 'Phone notifications', 'ycc:send-emails' => 'Club emails', 'ycc:event-reminders' => 'Event reminders', 'ycc:refresh-activity' => 'Member status refresh (nightly)'];
        @endphp
        <section class="panel p-5 sm:p-6" id="cron">
            <h2 class="text-3xl font-extrabold text-abyss-900">Cron job</h2>
            @if ($cronOk)
                <p class="mt-2 flex items-center gap-2 text-sm font-bold text-st-active"><x-icon name="check" class="size-4" /> Running. Last ran {{ $cronLast->diffForHumans() }}.</p>
                <p class="mt-2 text-sm text-ink-soft">Phone notifications, event reminders and batched emails go out every minute.</p>
            @elseif ($cronLast && $cronLast->gt(now()->subMinutes(5)))
                <p class="mt-2 text-sm font-bold text-st-at-risk">The cron job runs, but its jobs aren’t finishing.</p>
                <p class="mt-2 text-sm text-ink-soft">This usually clears within a minute of uploading an update. If it stays, send the developer the error below.</p>
            @else
                <p class="mt-2 text-sm font-bold text-st-at-risk">{{ $cronLast ? 'Not running. Last ran '.$cronLast->diffForHumans().'.' : 'Not set up yet.' }}</p>
                <p class="mt-2 text-sm text-ink-soft">Until it is, visits to the site send phone notifications, reminders and emails instead, at most once a minute, so they can be late when nobody is on the site. For reliable timing, add this in cPanel › Cron Jobs with “Once Per Minute” (* * * * *):</p>
                <div x-data="{ copied: false }" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <code class="min-w-0 flex-1 break-all rounded-md bg-abyss-900 px-3 py-2 font-mono text-xs text-white" x-ref="cmd">{{ \App\Support\Scheduler::cronCommand() }}</code>
                    <button type="button" class="btn-line btn-sm shrink-0" @click="navigator.clipboard?.writeText($refs.cmd.textContent).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"><x-icon name="copy" class="size-4" /> <span x-text="copied ? 'Copied' : 'Copy'">Copy</span></button>
                </div>
                <p class="mt-2 text-xs text-ink-soft">If “ea-php83” isn’t on your server, use the PHP 8.3 path cPanel shows under Cron Jobs or MultiPHP.</p>
            @endif

            @if ($cronLast)
                <ul class="mt-4 divide-y divide-glass-100 rounded-md border border-glass-100 text-sm">
                    @foreach ($jobs as $name => $label)
                        @php $ok = \App\Support\Scheduler::jobLastOk($name); @endphp
                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                            <span>{{ $label }}</span>
                            @php $nightly = $name === 'ycc:refresh-activity'; @endphp
                            <span class="text-xs {{ $ok || $nightly ? 'text-ink-soft' : 'font-bold text-st-at-risk' }}">{{ $ok ? 'Last worked '.$ok->diffForHumans() : ($nightly ? 'Runs nightly at 00:15' : 'Hasn’t run yet') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($cronError)
                <p class="mt-3 break-words rounded-md bg-st-inactive/10 px-3 py-2 text-xs text-st-inactive"><span class="font-bold">Last error ({{ $cronError['job'] }}, {{ $cronError['at']->diffForHumans() }}):</span> {{ $cronError['message'] }}</p>
            @endif
        </section>
    </div>
</x-layouts.app>
