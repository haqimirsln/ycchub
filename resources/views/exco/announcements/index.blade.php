<x-layouts.app title="Email members">
    <x-page-header title="Email members" subtitle="Promote an event, share club news or send a reminder. It goes out as a YCC email with your message and an optional button." />

    @if (! $ready)
        <div class="panel p-5 text-sm">Press <span class="font-bold">Update database</span> in <a href="{{ route('admin.settings.edit') }}" class="font-bold text-kelp-500 hover:underline">Settings</a> first to turn this on.</div>
    @else
        @php
            $initial = [
                'subject' => old('subject', ''),
                'body' => old('body', '<p>Hi {name},</p><p></p>'),
                'button_label' => old('button_label', ''),
                'button_url' => old('button_url', ''),
                'program_id' => (string) old('program_id', ''),
                'roles' => old('roles', []),
                'teams' => old('teams', []),
                'statuses' => old('statuses', []),
                'types' => old('types', []),
            ];
        @endphp

        <form method="POST" action="{{ route('exco.announcements.store') }}"
              class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
              x-data="composer(@js($initial), @js($templates), @js(route('exco.announcements.count')), {{ $everyone }}, @js(['image' => route('exco.announcements.image'), 'preview' => route('exco.announcements.preview')]))">
            @csrf

            {{-- The message --}}
            <div class="panel space-y-5 p-5 sm:p-6">
                <div>
                    <p class="field-label">Start from</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(t, key) in templates" :key="key">
                            <button type="button" class="rounded-full border px-3 py-1.5 text-sm font-bold transition-colors"
                                    :class="picked === key ? 'border-abyss-900 bg-abyss-900 text-white' : 'border-glass-200 bg-white text-ink hover:border-abyss-700'"
                                    @click="useTemplate(key)" x-text="t.label"></button>
                        </template>
                    </div>
                </div>

                <div>
                    <label for="subject" class="field-label">Subject</label>
                    <input id="subject" name="subject" x-model="f.subject" maxlength="150" class="field" placeholder="e.g. Beach cleanup this Saturday, join us!" required>
                    @error('subject') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="field-label" id="body-label">Message</p>
                    <input type="hidden" name="body" :value="f.body">
                    <div class="overflow-hidden rounded-md border border-glass-200 bg-white focus-within:border-abyss-700 focus-within:ring-2 focus-within:ring-abyss-700/15"
                         @dragover.prevent @drop.prevent="ed && ed.addFiles($event.dataTransfer.files)">
                        {{-- Toolbar --}}
                        <div class="flex flex-wrap items-center gap-0.5 border-b border-glass-200 bg-glass-50 px-1.5 py-1.5" role="toolbar" aria-label="Formatting">
                            <select class="mr-1 h-9 rounded-md border border-glass-200 bg-white px-2 text-sm" aria-label="Text style" :value="s.block" @change="ed.block($event.target.value)">
                                <option value="p">Normal text</option>
                                <option value="h2">Heading</option>
                                <option value="h3">Small heading</option>
                            </select>
                            @foreach ([
                                ['bold', 'Bold (Ctrl+B)', '<span class="font-extrabold">B</span>'],
                                ['italic', 'Italic (Ctrl+I)', '<span class="font-serif italic">I</span>'],
                                ['underline', 'Underline (Ctrl+U)', '<span class="underline">U</span>'],
                                ['strike', 'Strikethrough', '<span class="line-through">S</span>'],
                            ] as [$mark, $title, $glyph])
                                <button type="button" class="tb-btn text-base" title="{{ $title }}" aria-label="{{ $title }}" :aria-pressed="s.{{ $mark }}" :class="s.{{ $mark }} && 'is-on'" @click="ed.toggle('{{ $mark }}')">{!! $glyph !!}</button>
                            @endforeach

                            {{-- Colour --}}
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                                <button type="button" class="tb-btn flex-col gap-0" title="Text colour" aria-label="Text colour" @click="open = !open">
                                    <span class="text-base font-bold leading-none">A</span>
                                    <span class="mt-0.5 h-1 w-4 rounded-full" :style="`background:${s.color || '#1f2a28'}`"></span>
                                </button>
                                <div x-cloak x-show="open" x-transition.opacity.duration.100ms class="absolute left-0 top-full z-20 mt-1 w-48 rounded-lg border border-glass-200 bg-white p-2 shadow-lg">
                                    <div class="grid grid-cols-6 gap-1.5">
                                        @foreach (['#1f2a28' => 'Black', '#12424d' => 'Deep sea', '#1f6f61' => 'Sea green', '#b07d0c' => 'Gold', '#c0392b' => 'Red', '#2563eb' => 'Blue'] as $hex => $name)
                                            <button type="button" class="size-6 rounded-full ring-offset-2 hover:ring-2 hover:ring-abyss-700" style="background: {{ $hex }}" title="{{ $name }}" aria-label="{{ $name }}" @click="ed.color('{{ $hex }}'); open = false"></button>
                                        @endforeach
                                    </div>
                                    <button type="button" class="mt-2 w-full rounded-md px-2 py-1 text-left text-sm text-ink-soft hover:bg-glass-50" @click="ed.color(''); open = false">Default colour</button>
                                </div>
                            </div>

                            <span class="mx-1 h-6 w-px bg-glass-200" aria-hidden="true"></span>
                            @foreach ([
                                ['left', 'Align left', '<path d="M4 6h16M4 10h10M4 14h16M4 18h10"/>'],
                                ['center', 'Centre', '<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>'],
                                ['right', 'Align right', '<path d="M4 6h16M10 10h10M4 14h16M10 18h10"/>'],
                            ] as [$where, $title, $path])
                                <button type="button" class="tb-btn" title="{{ $title }}" aria-label="{{ $title }}" :aria-pressed="s.align === '{{ $where }}'" :class="s.align === '{{ $where }}' && 'is-on'" @click="ed.align('{{ $where }}')"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">{!! $path !!}</svg></button>
                            @endforeach

                            <span class="mx-1 h-6 w-px bg-glass-200" aria-hidden="true"></span>
                            @foreach ([
                                ['bulletList', 'Bullet list', '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1" fill="currentColor"/><circle cx="4.5" cy="12" r="1" fill="currentColor"/><circle cx="4.5" cy="18" r="1" fill="currentColor"/>'],
                                ['orderedList', 'Numbered list', '<path d="M10 6h10M10 12h10M10 18h10"/><path d="M4 4.5h1.5V9M3.8 9h3.2M4 13.2a1.6 1.6 0 1 1 2.6 1.3L4 17.5h3" stroke-width="1.4"/>'],
                                ['blockquote', 'Quote', '<path d="M5 7h5v5H7a2 2 0 0 0 2 3M14 7h5v5h-3a2 2 0 0 0 2 3"/>'],
                            ] as [$mark, $title, $path])
                                <button type="button" class="tb-btn" title="{{ $title }}" aria-label="{{ $title }}" :aria-pressed="s.{{ $mark }}" :class="s.{{ $mark }} && 'is-on'" @click="ed.toggle('{{ $mark }}')"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $path !!}</svg></button>
                            @endforeach
                            <button type="button" class="tb-btn" title="Divider line" aria-label="Divider line" @click="ed.divider()"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3 12h18"/><path d="M8 7h8M8 17h8" stroke-width="1.2" opacity=".5"/></svg></button>

                            <span class="mx-1 h-6 w-px bg-glass-200" aria-hidden="true"></span>
                            <button type="button" class="tb-btn" title="Add or edit a link" aria-label="Link" :class="s.link && 'is-on'" @click="ed.link()"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg></button>
                            <label class="tb-btn cursor-pointer" title="Add a picture" :class="uploading && 'is-busy'">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 18 5-5 4 4 2.5-2.5L20 19"/></svg>
                                <span class="sr-only">Add a picture</span>
                                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple class="sr-only" @change="ed.addFiles($event.target.files); $event.target.value = ''">
                            </label>
                            <button type="button" class="tb-btn w-auto gap-1 px-2 text-sm font-bold" title="Insert the member's first name" @click="ed.insertName()"><x-icon name="user" class="size-4" /> Name</button>

                            <span class="ml-auto flex items-center gap-0.5">
                                <button type="button" class="tb-btn" title="Remove formatting" aria-label="Remove formatting" @click="ed.clear()"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 5h12M12 5l-3 14M15 15l5 5M20 15l-5 5"/></svg></button>
                                <button type="button" class="tb-btn" title="Undo (Ctrl+Z)" aria-label="Undo" :disabled="!s.canUndo" @click="ed.undo()"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg></button>
                                <button type="button" class="tb-btn" title="Redo (Ctrl+Y)" aria-label="Redo" :disabled="!s.canRedo" @click="ed.redo()"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg></button>
                            </span>
                        </div>

                        {{-- Writing area --}}
                        <div x-ref="editor" aria-labelledby="body-label"></div>
                        <p x-show="uploading" class="border-t border-glass-100 px-4 py-2 text-sm text-ink-soft">Adding picture…</p>
                    </div>
                    <p x-show="uploadError" x-text="uploadError" class="field-error"></p>
                    @error('body') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="program_id" class="field-label">Add an event card <span class="font-normal text-ink-soft">(optional)</span></label>
                    <select id="program_id" name="program_id" x-model="f.program_id" class="field">
                        <option value="">No event</option>
                        @foreach ($events as $e)<option value="{{ $e->id }}">{{ $e->starts_at->format('j M') }} · {{ $e->title }}</option>@endforeach
                    </select>
                    <p class="field-hint">Shows the event's name, date and place, with a "View and register" button if you don't add your own below.</p>
                    @error('program_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)]">
                    <div>
                        <label for="button_label" class="field-label">Button text <span class="font-normal text-ink-soft">(optional)</span></label>
                        <input id="button_label" name="button_label" x-model="f.button_label" maxlength="40" class="field" placeholder="e.g. Register now">
                        @error('button_label') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="button_url" class="field-label">Button link</label>
                        <input id="button_url" name="button_url" type="url" x-model="f.button_url" maxlength="500" class="field" placeholder="https://">
                        @error('button_url') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Who gets it --}}
            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <div class="panel space-y-4 p-5">
                    <h2 class="text-2xl font-extrabold text-abyss-900">Who gets it</h2>
                    <p class="-mt-2 text-sm text-ink-soft">Leave a group empty to include everyone in it.</p>

                    @foreach ([
                        ['roles', 'Role', ['member' => 'Members', 'mt' => 'MT', 'advisor' => 'Advisor']],
                        ['teams', 'Team', collect(config('ycc.teams'))->map(fn ($n) => \Illuminate\Support\Str::before($n, ' Team'))->all() + ['none' => 'No team yet']],
                        ['statuses', 'Activity', \App\Models\User::ACTIVITY_LABELS],
                        ['types', 'Type', ['student' => 'Students', 'alumni' => 'Alumni']],
                    ] as [$field, $label, $options])
                        <fieldset>
                            <legend class="mb-1.5 text-xs font-bold uppercase tracking-wide text-ink-soft">{{ $label }}</legend>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($options as $value => $text)
                                    <label class="cursor-pointer rounded-full border border-glass-200 bg-white px-3 py-1 text-sm transition-colors has-[:checked]:border-abyss-900 has-[:checked]:bg-abyss-900 has-[:checked]:text-white">
                                        <input type="checkbox" name="{{ $field }}[]" value="{{ $value }}" x-model="f.{{ $field }}" class="sr-only"> {{ $text }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="rounded-md bg-glass-50 px-4 py-3">
                        <p><span class="font-display text-4xl font-extrabold leading-none text-abyss-900" x-text="count ?? '…'">{{ $everyone }}</span> <span class="text-sm text-ink-soft" x-text="count === 1 ? 'member gets this' : 'members get this'">members get this</span></p>
                        @if ($optedOut)<p class="mt-1 text-xs text-ink-soft">{{ $optedOut }} {{ $optedOut === 1 ? 'member has' : 'members have' }} turned club emails off and won't get it.</p>@endif
                    </div>

                    <div class="grid gap-2">
                        <button type="button" class="btn-line w-full" @click="openPreview()" :class="previewLoading && 'is-busy'">Preview email</button>
                        <button type="submit" name="action" value="test" class="btn-line w-full"><x-icon name="inbox" class="size-4" /> Send a test to me</button>
                        <button type="submit" name="action" value="send" x-ref="send" class="btn-gold w-full py-3 text-base" :disabled="!count"
                                @click="if (!confirm(`Send &quot;${f.subject || 'this email'}&quot; to ${count} ${count === 1 ? 'member' : 'members'}?`)) $event.preventDefault()">
                            <x-icon name="send" class="size-4" /> <span x-text="count ? `Send to ${count}` : 'Nobody matches'">Send</span>
                        </button>
                    </div>
                    <p class="text-xs text-ink-soft">Sends {{ \App\Services\AnnouncementService::BATCH }} right away, then {{ \App\Services\AnnouncementService::BATCH }} a minute, so the mail server doesn't block us.</p>
                </div>
            </aside>

            {{-- Preview --}}
            <div x-cloak x-show="preview.open" x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-center justify-center bg-abyss-950/70 p-3 sm:p-6"
                 role="dialog" aria-modal="true" aria-label="Email preview" @keydown.escape.window="preview.open = false" @click.self="preview.open = false">
                <div class="flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-glass-100 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-ink-soft">Preview</p>
                            <p class="truncate font-bold text-ink" x-text="preview.subject"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="segmented" role="group" aria-label="Screen size">
                                <button type="button" class="segment" :class="preview.width === 'phone' && 'is-active'" :aria-pressed="preview.width === 'phone'" @click="preview.width = 'phone'">Phone</button>
                                <button type="button" class="segment" :class="preview.width === 'desktop' && 'is-active'" :aria-pressed="preview.width === 'desktop'" @click="preview.width = 'desktop'">Computer</button>
                            </div>
                            <button type="button" class="flex size-9 items-center justify-center rounded-full text-ink-soft hover:bg-glass-50" aria-label="Close preview" @click="preview.open = false"><x-icon name="x" class="size-5" /></button>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto bg-glass-100 p-3 sm:p-5">
                        <iframe title="Email preview" class="mx-auto block h-[65svh] rounded-lg border border-glass-200 bg-white transition-[width] duration-200"
                                :style="`width: ${preview.width === 'phone' ? '390px' : '100%'}; max-width: 100%`" :srcdoc="preview.html" sandbox="allow-popups allow-popups-to-escape-sandbox"></iframe>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-glass-100 px-4 py-3">
                        <p class="text-xs text-ink-soft">Shown with your name in place of each member's.</p>
                        <div class="flex gap-2">
                            <button type="button" class="btn-line btn-sm" @click="preview.open = false">Keep editing</button>
                            <button type="button" class="btn-gold btn-sm" :disabled="!count" @click="preview.open = false; $refs.send.click()" x-text="count ? `Send to ${count}` : 'Nobody matches'"></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Sent before --}}
        <section class="mt-10">
            <h2 class="mb-4 text-3xl font-extrabold text-abyss-900">Sent emails</h2>
            @if ($history->isEmpty())
                <div class="panel px-6 py-10 text-center text-ink-soft">Emails you send show up here, with how many went out.</div>
            @else
                <ul class="panel divide-y divide-glass-100">
                    @foreach ($history as $a)
                        <li class="px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                <div class="min-w-0">
                                    <p class="break-words font-bold text-ink">{{ $a->subject }}</p>
                                    <p class="text-xs text-ink-soft">{{ $a->created_at->format('j M Y, g:i a') }} · {{ $a->sender?->full_name ?? 'Someone' }} · To: {{ $a->audience_label }}</p>
                                </div>
                                <p class="shrink-0 text-sm">
                                    @if ($a->isSending())
                                        <span class="font-bold text-crest-700">Sending</span>
                                    @elseif ($a->cancelled_at)
                                        <span class="font-bold text-st-inactive">Stopped</span>
                                    @endif
                                    <span class="text-ink-soft"><span class="font-bold text-ink">{{ $a->sent_count }}</span> of {{ $a->recipient_count }} sent{{ $a->failed_count ? ' · '.$a->failed_count.' failed' : '' }}</span>
                                </p>
                            </div>
                            @if ($a->isSending())
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-glass-100"><div class="h-full rounded-full bg-crest-500" style="width: {{ $a->progress() }}%"></div></div>
                                <p class="mt-2 text-xs text-ink-soft">{{ $a->remaining() }} left. If this doesn't move after a few minutes, the cron job isn't running, so press Send next batch.</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('exco.announcements.next', $a) }}">@csrf <button class="btn-dark btn-sm">Send next batch</button></form>
                                    <form method="POST" action="{{ route('exco.announcements.cancel', $a) }}" onsubmit="return confirm('Stop sending? Members not emailed yet won\'t get it.')">@csrf @method('DELETE') <button class="btn-quiet-danger btn-sm">Stop</button></form>
                                </div>
                            @endif
                            @if ($a->failed_count && $a->last_error)
                                <p class="mt-2 break-words text-xs text-st-inactive">Last failure: {{ $a->last_error }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @push('scripts')
        <script>
            function composer(initial, templates, countUrl, everyone, urls) {
                let ed = null;
                return {
                    f: initial,
                    templates,
                    picked: null,
                    count: everyone,
                    timer: null,
                    s: {},
                    uploading: false,
                    uploadError: '',
                    previewLoading: false,
                    preview: { open: false, html: '', subject: '', width: 'phone' },
                    get ed() { return ed; },
                    init() {
                        ['roles', 'teams', 'statuses', 'types'].forEach((k) => this.$watch(`f.${k}`, () => this.recount()));
                        if (['roles', 'teams', 'statuses', 'types'].some((k) => this.f[k].length)) this.recount();
                        const mount = () => {
                            ed = window.createEmailEditor(this.$refs.editor, {
                                content: this.f.body,
                                uploadUrl: urls.image,
                                onChange: (html) => { this.f.body = html; },
                                onState: (state) => { this.s = state; },
                                onUploading: (on) => { this.uploading = on; if (on) this.uploadError = ''; },
                                onError: (msg) => { this.uploadError = msg; },
                            });
                        };
                        window.createEmailEditor ? mount() : window.addEventListener('email-editor-ready', mount, { once: true });
                    },
                    useTemplate(key) {
                        const t = this.templates[key];
                        const written = this.f.subject.trim() !== '' || (ed && ed.text().replace(/Hi \{name\},?/, '').trim() !== '');
                        if (written && !confirm('Replace what you wrote with this template?')) return;
                        this.picked = key;
                        Object.assign(this.f, {
                            subject: t.subject, button_label: t.button_label, button_url: t.button_url,
                            roles: t.audience.roles ?? [], teams: t.audience.teams ?? [], statuses: t.audience.statuses ?? [], types: t.audience.types ?? [],
                        });
                        ed?.setContent(t.body);
                        if (t.needs_event) this.$nextTick(() => document.getElementById('program_id').focus());
                    },
                    async openPreview() {
                        this.previewLoading = true;
                        try {
                            const r = await fetch(urls.preview, {
                                method: 'POST',
                                body: new FormData(this.$root),
                                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            });
                            if (r.status === 422) {
                                const data = await r.json();
                                alert(Object.values(data.errors ?? {}).flat()[0] ?? 'Add a subject and a message first.');
                                return;
                            }
                            if (!r.ok) throw new Error();
                            this.preview.html = await r.text();
                            this.preview.subject = (this.f.subject || '').replaceAll('{name}', @js(auth()->user()->firstName()));
                            this.preview.open = true;
                        } catch (e) {
                            alert("Couldn't load the preview. Check your connection and try again.");
                        } finally {
                            this.previewLoading = false;
                        }
                    },
                    recount() {
                        clearTimeout(this.timer);
                        this.count = null;
                        this.timer = setTimeout(async () => {
                            const q = new URLSearchParams();
                            ['roles', 'teams', 'statuses', 'types'].forEach((k) => this.f[k].forEach((v) => q.append(`${k}[]`, v)));
                            try {
                                const r = await fetch(`${countUrl}?${q}`, { headers: { Accept: 'application/json' } });
                                this.count = (await r.json()).count;
                            } catch (e) { this.count = 0; }
                        }, 250);
                    },
                };
            }
        </script>
        @vite('resources/js/email-editor.js')
    @endpush
</x-layouts.app>
