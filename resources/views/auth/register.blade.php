<x-layouts.auth title="Join YCC" heading="Join the club" :wide="true">
    <p class="mt-2 text-ink-soft">The MT checks every sign-up before it goes live. You'll get an email once you're approved.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-8">
        @csrf
        <fieldset class="grid gap-5 sm:grid-cols-2">
            <legend class="mb-4 font-display text-2xl font-extrabold text-abyss-900 sm:col-span-2">About you</legend>
            <div class="sm:col-span-2">
                <label for="full_name" class="field-label">Full name (as in your matric card)</label>
                <input id="full_name" name="full_name" value="{{ old('full_name') }}" class="field" autocomplete="name" required>
                @error('full_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="matric_no" class="field-label">Matric number</label>
                <input id="matric_no" name="matric_no" value="{{ old('matric_no') }}" class="field uppercase" autocapitalize="characters" required>
                @error('matric_no') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone_number" class="field-label">Phone (WhatsApp)</label>
                <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number') }}" class="field" placeholder="0123456789" autocomplete="tel" required>
                @error('phone_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="field" autocomplete="email" required>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <fieldset class="space-y-5" x-data="{ type: @js(old('member_type', 'student')) }">
            <legend class="mb-4 font-display text-2xl font-extrabold text-abyss-900">Your place in YCC</legend>
            <div>
                <p class="field-label">I am a</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\Illuminate\Support\Arr::only(\App\Models\User::MEMBER_TYPES, ['student', 'alumni']) as $key => $label)
                        <label class="choice-card items-center">
                            <input type="radio" name="member_type" value="{{ $key }}" class="sr-only" x-model="type" required>
                            <span class="choice-dot" aria-hidden="true"></span>
                            <span class="text-sm font-bold text-ink">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="field-hint" x-show="type === 'alumni'" x-cloak>Alumni: use the matric number you had as a student.</p>
                @error('member_type') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <x-team-picker :selected="old('team')" />

            <x-faculty-input :value="old('faculty')" />

            <div class="grid gap-5" :class="type === 'alumni' ? '' : 'sm:grid-cols-[1fr_9rem]'">
                <div>
                    <label for="programme" class="field-label">Programme <span class="font-normal text-ink-soft">(optional)</span></label>
                    <input id="programme" name="programme" value="{{ old('programme') }}" class="field" placeholder="e.g. Marine Biology">
                    @error('programme') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div x-show="type !== 'alumni'">
                    <label for="year_of_study" class="field-label">Year</label>
                    <select id="year_of_study" name="year_of_study" class="field">
                        <option value="">-</option>
                        @for ($y = 1; $y <= 5; $y++)
                            <option value="{{ $y }}" @selected(old('year_of_study') == $y)>Year {{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="grid gap-5 sm:grid-cols-2">
            <legend class="mb-4 font-display text-2xl font-extrabold text-abyss-900 sm:col-span-2">Password</legend>
            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" class="field" autocomplete="new-password" required>
                <p class="field-hint">At least 8 characters, with letters and numbers.</p>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Type it again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required>
            </div>
        </fieldset>

        <div class="rounded-lg border border-glass-200 bg-white p-4">
            <label class="flex items-start gap-3 text-sm text-ink">
                <input type="checkbox" name="pdpa_consent" value="1" class="mt-0.5 size-4 shrink-0 accent-abyss-900" @checked(old('pdpa_consent')) required>
                <span>
                    I agree that YCC may keep my name, matric number, phone, email and attendance record to run club events and
                    contact me about club activities, in line with the Personal Data Protection Act 2010. Only the YCC MT can see my
                    contact details, and I can ask for my data to be removed.
                </span>
            </label>
            @error('pdpa_consent') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-dark w-full py-3 text-base">Send sign-up for approval</button>
    </form>

    <p class="mt-8 text-center text-ink-soft">
        Already a member? <a href="{{ route('login') }}" class="font-bold text-abyss-900 underline decoration-crest-500 decoration-2 underline-offset-4">Log in</a>
    </p>
</x-layouts.auth>
