<x-layouts.app title="My profile">
    <x-page-header title="My profile" subtitle="Keep your phone number current so the MT can reach you about events." />

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[1fr_22rem]">
        <form method="POST" action="{{ route('profile.update') }}" class="panel space-y-5 p-5 sm:p-6">
            @csrf @method('PUT')
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="full_name" class="field-label">Full name</label>
                    <input id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" class="field" required>
                    @error('full_name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="field-label">Matric number</label>
                    <input value="{{ $user->matric_no }}" class="field bg-glass-50 text-ink-soft" disabled>
                    <p class="field-hint">Ask an admin if this is wrong.</p>
                </div>
                <div>
                    <label for="phone_number" class="field-label">Phone (WhatsApp)</label>
                    <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $user->phone_number) }}" class="field" required>
                    @error('phone_number') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="email" class="field-label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="field" required>
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                @if ($user->isAdvisor())
                    <input type="hidden" name="member_type" value="staff">
                @else
                <div class="scroll-mt-24 sm:col-span-2 {{ $user->team ? '' : 'rounded-lg border-2 border-crest-500/60 bg-crest-300/15 p-3 sm:p-4' }}" id="team">
                    @unless ($user->team)
                        <p class="mb-3 text-sm text-ink"><span class="font-bold">Pick your YCC team.</span> Tap one below, then press <span class="font-bold">Save profile</span> at the bottom. You can change it later.</p>
                    @endunless
                    <x-team-picker :selected="old('team', $user->team)" />
                </div>
                <div class="sm:col-span-2">
                    <p class="field-label">I am a</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (\Illuminate\Support\Arr::only(\App\Models\User::MEMBER_TYPES, ['student', 'alumni']) as $key => $label)
                            <label class="choice-card items-center">
                                <input type="radio" name="member_type" value="{{ $key }}" class="sr-only" @checked(old('member_type', $user->member_type ?? 'student') === $key)>
                                <span class="choice-dot" aria-hidden="true"></span>
                                <span class="text-sm font-bold text-ink">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
                <div class="sm:col-span-2">
                    <x-faculty-input :value="old('faculty', $user->facultyName())" />
                </div>
                <div class="grid grid-cols-[1fr_7rem] gap-3 sm:col-span-2">
                    <div>
                        <label for="programme" class="field-label">Programme</label>
                        <input id="programme" name="programme" value="{{ old('programme', $user->programme) }}" class="field">
                    </div>
                    <div>
                        <label for="year_of_study" class="field-label">Year</label>
                        <select id="year_of_study" name="year_of_study" class="field">
                            <option value="">-</option>
                            @for ($y = 1; $y <= 5; $y++)<option value="{{ $y }}" @selected(old('year_of_study', $user->year_of_study) == $y)>{{ $y }}</option>@endfor
                        </select>
                    </div>
                </div>
                @if (\App\Support\DatabaseStatus::hasColumn('users', 'email_opt_out'))
                    <div class="scroll-mt-24 sm:col-span-2" id="emails">
                        <input type="hidden" name="email_news" value="0">
                        <label class="flex items-start gap-3 rounded-md border border-glass-200 p-3">
                            <input type="checkbox" name="email_news" value="1" class="mt-0.5 size-4 accent-abyss-900" @checked(old('email_news', ! $user->email_opt_out))>
                            <span><span class="block text-sm font-bold text-ink">Email me club news</span><span class="block text-sm text-ink-soft">Event promotions and updates from the MT. Account emails like password resets always come through.</span></span>
                        </label>
                    </div>
                @endif
            </div>
            <button type="submit" class="btn-dark">Save profile</button>
        </form>

        {{-- On phones the picture comes first, above the details form. --}}
        <div class="contents lg:block lg:space-y-6 lg:self-start">
        {{-- Profile picture --}}
        <section class="panel order-first p-5 sm:p-6 lg:order-none" x-data="{ preview: null, pick(e) { const f = e.target.files[0]; if (!f) return; this.preview = URL.createObjectURL(f); } }">
            <h2 class="text-2xl font-extrabold text-abyss-900">Profile picture</h2>
            <p class="mt-1 text-sm text-ink-soft">Helps the MT put a face to your name at events.</p>
            <div class="mt-4 flex items-center gap-4">
                <div class="relative shrink-0">
                    <template x-if="preview"><img :src="preview" alt="" class="size-28 rounded-full object-cover"></template>
                    <div x-show="!preview"><x-avatar :user="$user" size="2xl" /></div>
                </div>
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="min-w-0 space-y-2" x-ref="photoForm">
                    @csrf
                    <label class="btn-line btn-sm cursor-pointer">
                        <x-icon name="edit" class="size-4" /> {{ $user->avatarUrl() ? 'Change photo' : 'Choose photo' }}
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)">
                    </label>
                    <button type="submit" class="btn-dark btn-sm flex" x-cloak x-show="preview">Save photo</button>
                    <p class="text-xs text-ink-soft">JPG, PNG or WebP, up to 5 MB. We crop it to a square.</p>
                </form>
            </div>
            @error('photo') <p class="field-error">{{ $message }}</p> @enderror
            @if ($user->avatarUrl())
                <form method="POST" action="{{ route('profile.photo.remove') }}" class="mt-3" x-show="!preview">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm font-bold text-st-inactive hover:underline">Remove photo</button>
                </form>
            @endif
        </section>

        @include('partials.push-toggle')

        <form method="POST" action="{{ route('profile.password') }}" class="panel space-y-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="text-2xl font-extrabold text-abyss-900">Change password</h2>
            <div>
                <label for="current_password" class="field-label">Current password</label>
                <input id="current_password" name="current_password" type="password" class="field" autocomplete="current-password" required>
                @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="field-label">New password</label>
                <input id="password" name="password" type="password" class="field" autocomplete="new-password" required>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Type it again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn-line w-full">Change password</button>
        </form>

        @php $mtHistory = app(\App\Services\MtService::class)->history($user); @endphp
        @if ($mtHistory->isNotEmpty())
            <section class="panel self-start p-5 sm:p-6">
                <h2 class="text-2xl font-extrabold text-abyss-900">Your YCC record</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($mtHistory as $term)
                        <li class="flex items-center gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-crest-300/40 text-crest-700"><x-icon name="shield" class="size-4" /></span><span><span class="block font-bold text-ink">{{ $term->label() }}</span><span class="text-sm text-ink-soft">Majlis Tertinggi, Sesi {{ $term->session }}</span></span></li>
                    @endforeach
                </ul>
            </section>
        @endif
        </div>
    </div>
</x-layouts.app>
