@php $editing = $program->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit event' : 'Create event'">
    <x-page-header :title="$editing ? 'Edit event' : 'Create event'" :back="$editing ? [route('programs.show', $program), $program->title] : [route('exco.programs.index'), 'Manage events']" />

    @php $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : ''; @endphp

    <form method="POST" action="{{ $editing ? route('exco.programs.update', $program) : route('exco.programs.store') }}" enctype="multipart/form-data"
          class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[1fr_20rem]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6">
        <div class="panel space-y-5 p-5 sm:p-6">
            <div>
                <label for="title" class="field-label">Title</label>
                <input id="title" name="title" value="{{ old('title', $program->title) }}" class="field" placeholder="e.g. Teluk Ketapang cleanup" required>
                @error('title') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="category" class="field-label">Category</label>
                <select id="category" name="category" class="field">
                    @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category', $program->category) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>

            {{-- Where it happens --}}
            @php $oldLat = old('latitude', $program->latitude); $oldLng = old('longitude', $program->longitude); @endphp
            <fieldset class="rounded-lg border border-glass-200 bg-glass-50/60 p-4 sm:p-5"
                      x-data="wherePicker({
                          type: @js(old('location_type', $program->locationType())),
                          lat: @js($oldLat !== null && $oldLat !== '' ? (float) $oldLat : null),
                          lng: @js($oldLng !== null && $oldLng !== '' ? (float) $oldLng : null),
                          venue: @js((string) old('venue', $program->isOnline() ? '' : $program->venue)),
                          linkUrl: @js(route('exco.programs.map-link')),
                      })">
                <legend class="sr-only">Where it happens</legend>
                <p class="font-display text-2xl font-extrabold text-abyss-900">Where</p>

                @unless ($locationReady)
                    <p class="mt-2 rounded-md border border-crest-500 bg-crest-300/25 px-3 py-2 text-sm">
                        Online events and map pins switch on after the database update.
                        @can('admin')<a href="{{ route('admin.settings.edit') }}#database" class="font-bold underline">Update database</a>@else Ask an admin to press "Update database" in Settings.@endcan
                    </p>
                @endunless

                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="How people attend">
                    @foreach ([
                        'in_person' => ['pin', 'In person', 'At a place'],
                        'online' => ['video', 'Online', 'Webex, Zoom, Meet'],
                        'hybrid' => ['layers', 'Both', 'Place + online link'],
                    ] as $value => [$icon, $label, $hint])
                        <label class="choice-card items-center {{ ! $locationReady && $value !== 'in_person' ? 'pointer-events-none opacity-50' : '' }}">
                            <input type="radio" name="location_type" value="{{ $value }}" x-model="type" class="sr-only" @disabled(! $locationReady && $value !== 'in_person')>
                            <x-icon :name="$icon" class="size-5 shrink-0 text-kelp-500" />
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-ink">{{ $label }}</span>
                                <span class="block text-xs text-ink-soft">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('location_type') <p class="field-error">{{ $message }}</p> @enderror

                {{-- Place --}}
                <div x-show="needsPlace" class="mt-5 space-y-4">
                    <div>
                        <label for="venue" class="field-label">Venue</label>
                        <input id="venue" name="venue" x-model="venue" class="field" placeholder="e.g. Setiu Wetlands" :required="needsPlace">
                        <p class="field-hint">Shown to members as written. Type it the way you'd search for it in Google Maps.</p>
                        @error('venue') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    @if ($locationReady)
                        {{-- Google Maps preview: shows exactly what members will get --}}
                        <div>
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="field-label mb-0">Map preview</p>
                                <p class="text-xs text-ink-soft" x-text="hasPin ? 'Using your exact pin' : 'Google Maps search for the venue'"></p>
                            </div>
                            <div class="relative z-0 mt-1.5 h-64 overflow-hidden rounded-md border border-glass-300 bg-glass-100 sm:h-72">
                                <template x-if="mapQuery">
                                    <iframe :src="embedUrl" class="size-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Google Maps preview" allowfullscreen></iframe>
                                </template>
                                <div x-show="!mapQuery" class="flex size-full items-center justify-center p-6 text-center text-sm text-ink-soft">Type the venue above and the Google Maps preview shows up here.</div>
                            </div>
                            <p class="field-hint">Right place? You're done. Wrong place or not found? Set the exact spot below.</p>
                        </div>

                        {{-- Exact spot from Google Maps --}}
                        <div class="rounded-md border border-glass-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-bold">Exact spot <span class="font-normal text-ink-soft">(optional)</span></p>
                                <p x-cloak x-show="hasPin" class="flex items-center gap-2 text-sm text-st-active">
                                    <x-icon name="check" class="size-4" /> Pin set <span class="font-mono text-xs text-ink-soft" x-text="pinText"></span>
                                    <button type="button" @click="clearPin()" class="font-bold text-st-inactive hover:underline">Remove</button>
                                </p>
                            </div>
                            <ol class="mt-2 list-decimal space-y-0.5 pl-5 text-sm text-ink-soft">
                                <li><a :href="searchUrl" target="_blank" rel="noopener" class="font-bold text-kelp-500 underline-offset-4 hover:underline">Open Google Maps</a> and find the place (e.g. SEATRU, UMT).</li>
                                <li>Tap the place, then <span class="font-bold text-ink">Share</span>, then <span class="font-bold text-ink">Copy link</span>.</li>
                                <li>Paste it here and press Use link.</li>
                            </ol>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
                                <label for="map-link" class="sr-only">Google Maps link</label>
                                <input id="map-link" x-model="link" @keydown.enter.prevent="useLink()" @paste="setTimeout(() => useLink(), 60)" class="field" placeholder="Paste the link here">
                                <button type="button" class="btn-dark shrink-0" @click="useLink()" :disabled="linkBusy || !link.trim()" x-text="linkBusy ? 'Finding' : 'Use link'">Use link</button>
                            </div>
                            <p x-cloak x-show="linkError" x-text="linkError" class="field-error"></p>

                            <details class="mt-3 text-sm" @toggle="if ($event.target.open) mountMap()">
                                <summary class="cursor-pointer font-bold text-kelp-500">No name on Google Maps? Drop the pin yourself</summary>
                                <p class="mt-2 text-ink-soft">For a jetty, a stretch of beach or a meeting point. Tap the map to drop the pin, then drag it to the exact spot.</p>
                                <div x-ref="map" class="relative z-0 mt-2 h-64 overflow-hidden rounded-md border border-glass-300 bg-glass-100" role="application" aria-label="Map. Tap to place the pin, drag the pin to adjust."></div>
                            </details>
                        </div>

                        <input type="hidden" name="latitude" :value="needsPlace && hasPin ? lat : ''">
                        <input type="hidden" name="longitude" :value="needsPlace && hasPin ? lng : ''">
                        @error('latitude') <p class="field-error">{{ $message }}</p> @enderror
                    @endif
                </div>

                {{-- Online --}}
                @if ($locationReady)
                    <div x-cloak x-show="needsMeeting" class="mt-5 space-y-4">
                        <div>
                            <label for="meeting_url" class="field-label">Meeting link <span class="font-normal text-ink-soft">(can be added later)</span></label>
                            <input id="meeting_url" name="meeting_url" type="url" inputmode="url" value="{{ old('meeting_url', $program->meeting_url) }}" class="field" placeholder="https://umt.webex.com/meet/...">
                            <p class="field-hint">Only members who registered see the Join button, from 30 minutes before the start.</p>
                            @error('meeting_url') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="meeting_info" class="field-label">Meeting number or password <span class="font-normal text-ink-soft">(optional)</span></label>
                            <input id="meeting_info" name="meeting_info" value="{{ old('meeting_info', $program->meeting_info) }}" class="field" placeholder="e.g. Meeting number 2512 345 6789, password ycc2026">
                            @error('meeting_info') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
            </fieldset>
            <div>
                <label for="description" class="field-label">Description</label>
                <textarea id="description" name="description" rows="8" class="field" placeholder="What will members do? What should they bring? Transport?" required>{{ old('description', $program->description) }}</textarea>
                <p class="field-hint">Leave a blank line between paragraphs.</p>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="starts_at" class="field-label">Starts</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $dt($program->starts_at)) }}" class="field" required>
                    @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ends_at" class="field-label">Ends <span class="font-normal text-ink-soft">(optional)</span></label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $dt($program->ends_at)) }}" class="field">
                    @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="registration_closes_at" class="field-label">Registration closes <span class="font-normal text-ink-soft">(optional)</span></label>
                    <input id="registration_closes_at" name="registration_closes_at" type="datetime-local" value="{{ old('registration_closes_at', $dt($program->registration_closes_at)) }}" class="field">
                    <p class="field-hint">Leave empty to keep it open until the start time.</p>
                    @error('registration_closes_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="max_capacity" class="field-label">Seats <span class="font-normal text-ink-soft">(optional)</span></label>
                    <input id="max_capacity" name="max_capacity" type="number" min="1" value="{{ old('max_capacity', $program->max_capacity) }}" class="field" placeholder="No limit">
                    @error('max_capacity') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                @if ($hoursReady)
                    <div class="sm:col-span-2">
                        <label for="volunteer_hours" class="field-label">Volunteer hours <span class="font-normal text-ink-soft">(optional)</span></label>
                        <input id="volunteer_hours" name="volunteer_hours" type="number" min="0" max="500" step="0.5" inputmode="decimal" value="{{ old('volunteer_hours', $program->volunteer_hours) }}" class="field sm:max-w-48" placeholder="From the times">
                        <p class="field-hint">Leave empty to count from start to end time. Set it for overnight or multi-day trips, e.g. 16 for a two-day hatchery weekend.</p>
                        @error('volunteer_hours') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        </div>

        @if ($committeeReady)
            {{-- Committee (AJK) --}}
            <section class="panel p-5 sm:p-6" id="committee"
                     x-data="{ rows: @js(array_values((array) old('committee', $committeeRows))), add() { this.rows.push({ user_id: '', position: '' }); this.$nextTick(() => this.$root.querySelector('[data-row]:last-of-type input')?.focus()) } }">
                <input type="hidden" name="committee_present" value="1">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-extrabold text-abyss-900">Committee (AJK)</h2>
                        <p class="mt-1 text-sm text-ink-soft">Who runs this event. Any member can be AJK and earns AJK merit once the event is over.</p>
                    </div>
                    <div class="w-36">
                        <label for="committee_count" class="field-label">No. of committee</label>
                        <input id="committee_count" name="committee_count" type="number" min="0" max="500" class="field" value="{{ old('committee_count', $program->committee_count) }}" :placeholder="rows.length || 'e.g. 12'">
                        @error('committee_count') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <datalist id="ajk-positions">
                    @foreach (['Pengarah Program', 'Timbalan Pengarah', 'Setiausaha', 'Penolong Setiausaha', 'Bendahari', 'AJK Logistik', 'AJK Publisiti', 'AJK Multimedia', 'AJK Makanan', 'AJK Teknikal', 'AJK Protokol', 'AJK Aktiviti', 'AJK Keselamatan', 'AJK Pendaftaran'] as $pos)<option value="{{ $pos }}"></option>@endforeach
                </datalist>

                <div class="mt-4 space-y-2">
                    <template x-for="(row, i) in rows" :key="i">
                        <div data-row class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 rounded-md border border-glass-200 bg-glass-50/60 p-2 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_auto]">
                            <input class="field" list="ajk-positions" :name="`committee[${i}][position]`" x-model="row.position" placeholder="Position, e.g. Pengarah Program" aria-label="Position">
                            <select class="field col-span-1 row-start-2 sm:row-start-auto" :name="`committee[${i}][user_id]`" x-model="row.user_id" aria-label="Member">
                                <option value="">Not decided yet</option>
                                @foreach ($memberOptions as $opt)<option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>@endforeach
                            </select>
                            <button type="button" class="row-span-2 self-center rounded-md p-2 text-ink-soft hover:text-st-inactive sm:row-span-1" @click="rows.splice(i, 1)" :aria-label="`Remove ${row.position || 'this row'}`"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    </template>
                    <p x-show="!rows.length" class="rounded-md border border-dashed border-glass-200 px-3 py-4 text-center text-sm text-ink-soft">No AJK added yet. You can add them later too.</p>
                </div>
                @error('committee.*') <p class="field-error">{{ $message }}</p> @enderror
                <button type="button" class="btn-line btn-sm mt-3" @click="add()"><x-icon name="plus" class="size-4" /> Add AJK</button>
            </section>
        @endif
        </div>

        <aside class="space-y-4 lg:self-start">
            <div class="panel space-y-4 p-5" x-data="{ status: @js(old('status', $program->status)) }" @change="if ($event.target.name === 'status') status = $event.target.value">
                @if ($program->status === 'cancelled')
                    <x-status-badge status="cancelled" />
                    <input type="hidden" name="status" value="draft">
                @else
                    <fieldset>
                        <legend class="field-label">Visibility</legend>
                        <label class="flex items-start gap-2 rounded-md border border-glass-200 p-3 has-[:checked]:border-abyss-700 has-[:checked]:bg-glass-50">
                            <input type="radio" name="status" value="published" class="mt-1 accent-abyss-900" @checked(old('status', $program->status) === 'published')>
                            <span><span class="block font-bold">Publish</span><span class="text-sm text-ink-soft">Members can see it and register.</span></span>
                        </label>
                        <label class="mt-2 flex items-start gap-2 rounded-md border border-glass-200 p-3 has-[:checked]:border-abyss-700 has-[:checked]:bg-glass-50">
                            <input type="radio" name="status" value="draft" class="mt-1 accent-abyss-900" @checked(old('status', $program->status) === 'draft')>
                            <span><span class="block font-bold">Save as draft</span><span class="text-sm text-ink-soft">Only the MT can see it.</span></span>
                        </label>
                    </fieldset>
                @endif
                <label class="flex items-start gap-2 text-sm">
                    <input type="hidden" name="counts_for_activity" value="0">
                    <input type="checkbox" name="counts_for_activity" value="1" class="mt-0.5 size-4 accent-abyss-900" @checked(old('counts_for_activity', $program->counts_for_activity))>
                    <span><span class="font-bold">Counts toward activity status</span><span class="block text-ink-soft">Untick for things like AGMs or MT-only meetings.</span></span>
                </label>

                @if ($pushReady && $program->status !== 'cancelled')
                    <div class="border-t border-glass-100 pt-4">
                        @if ($program->pushed_at)
                            <p class="rounded-md bg-glass-50 px-3 py-2 text-sm text-ink-soft">
                                <x-icon name="smartphone" class="mr-1 inline size-4 text-kelp-500" />
                                Phone notification sent on {{ $program->pushed_at->format('j M, g:i A') }}.
                            </p>
                        @else
                            <label class="flex items-start gap-2 text-sm">
                                <input type="hidden" name="notify_push" value="0">
                                <input type="checkbox" name="notify_push" value="1" class="mt-0.5 size-4 accent-abyss-900" @checked(old('notify_push', true))>
                                <span><span class="font-bold">Send a phone notification</span><span class="block text-ink-soft">“New event” pop-up for the {{ $pushMembers }} {{ $pushMembers === 1 ? 'member' : 'members' }} who turned notifications on. Sent once, when you publish.</span></span>
                            </label>
                        @endif
                    </div>
                @endif

                @if ($announceReady && $program->status !== 'cancelled')
                    <div class="border-t border-glass-100 pt-4" x-data="{ on: @js((bool) old('announce')) }">
                        @if ($announcement)
                            <p class="mb-2 rounded-md bg-glass-50 px-3 py-2 text-sm text-ink-soft">
                                <x-icon name="send" class="mr-1 inline size-4 text-kelp-500" />
                                Emailed to <span class="font-bold text-ink">{{ $announcement->recipient_count }}</span> {{ $announcement->recipient_count === 1 ? 'member' : 'members' }} on {{ $announcement->created_at->format('j M') }}.
                            </p>
                        @endif
                        <label class="flex items-start gap-2 text-sm">
                            <input type="hidden" name="announce" value="0">
                            <input type="checkbox" name="announce" value="1" x-model="on" class="mt-0.5 size-4 accent-abyss-900">
                            <span><span class="font-bold">{{ $announcement ? 'Email members again' : 'Email members about this event' }}</span><span class="block text-ink-soft">Sends the poster and details with a register button.</span></span>
                        </label>
                        <div x-cloak x-show="on" x-transition.opacity.duration.150ms class="mt-3 space-y-3">
                            <p x-show="status === 'draft'" class="rounded-md bg-st-risk/12 px-3 py-2 text-xs font-bold text-st-risk">Drafts aren't emailed. Choose Publish to send it.</p>
                            <div>
                                <label for="announce_team" class="field-label">Send to</label>
                                <select id="announce_team" name="announce_team" class="field">
                                    <option value="">All members</option>
                                    @foreach (config('ycc.teams') as $key => $team)<option value="{{ $key }}" @selected(old('announce_team') === $key)>{{ $team }} only</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label for="announce_note" class="field-label">Add a note <span class="font-normal text-ink-soft">(optional)</span></label>
                                <textarea id="announce_note" name="announce_note" rows="3" maxlength="1000" class="field" placeholder="e.g. Free transport from Pintu Utama at 7:30 AM.">{{ old('announce_note') }}</textarea>
                                @error('announce_note') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <p class="text-xs text-ink-soft">Members who turned club emails off won't get it. Want more control? Use <a href="{{ route('exco.announcements.index') }}" class="font-bold text-kelp-500 hover:underline">Email members</a>.</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="panel p-5" x-data="{ preview: @js($program->posterUrl()) }">
                <label for="poster" class="field-label">Poster <span class="font-normal text-ink-soft">(optional)</span></label>
                <template x-if="preview"><img :src="preview" alt="Poster preview" class="mb-3 w-full rounded border border-glass-200"></template>
                <input id="poster" name="poster" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-abyss-900 file:px-3 file:py-2 file:text-white"
                       @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview">
                <p class="field-hint">JPG, PNG or WebP, up to 3 MB.</p>
                @error('poster') <p class="field-error">{{ $message }}</p> @enderror
                @if ($program->poster_path)
                    <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_poster" value="1" class="accent-abyss-900"> Remove current poster</label>
                @endif
            </div>

            <button type="submit" class="btn-gold w-full py-3 text-base">{{ $editing ? 'Save changes' : 'Create event' }}</button>
        </aside>
    </form>

    @if ($locationReady)
        @push('scripts')
            @vite('resources/js/map.js')
        @endpush
    @endif

    @if ($editing && $program->status !== 'cancelled')
        <div class="mt-10 rounded-lg border border-st-inactive/30 bg-white p-5">
            <h2 class="font-sans text-lg font-bold">Cancel or delete</h2>
            <p class="mt-1 text-sm text-ink-soft">Cancelling keeps the record and shows registered members it was cancelled. Attendance at a cancelled event stops counting.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('exco.programs.cancel', $program) }}" onsubmit="return confirm('Cancel this event? Registered members will see it as cancelled.')">
                    @csrf <button class="btn-danger">Cancel event</button>
                </form>
                @can('superadmin')
                    <form method="POST" action="{{ route('exco.programs.destroy', $program) }}" onsubmit="return confirm('Delete this event and remove it from everyone\'s history?')">
                        @csrf @method('DELETE') <button class="btn-danger"><x-icon name="trash" class="size-4" /> Delete</button>
                    </form>
                @endcan
            </div>
        </div>
    @endif
</x-layouts.app>
