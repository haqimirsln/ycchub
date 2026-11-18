<x-layouts.auth title="Install YCC Hub" heading="Set up YCC Hub" :wide="true">
    <p class="mt-2 text-ink-soft">This page creates the database tables and the first admin account. It switches itself off once done.</p>

    @if ($dbError)
        <div class="mt-6 rounded-lg border border-st-inactive/40 bg-white p-4 text-sm">
            <p class="font-bold text-st-inactive">Can't connect to the database "{{ $dbName }}".</p>
            <p class="mt-1 text-ink-soft">Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env, and that the cPanel user is added to the database with All Privileges.</p>
            <p class="mt-2 break-all font-mono text-xs text-ink-soft">{{ $dbError }}</p>
        </div>
    @else
        <p class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-st-active"><x-icon name="check" class="size-4" /> Connected to database "{{ $dbName }}"</p>
    @endif

    <form method="POST" action="{{ route('install.run') }}" class="mt-8 space-y-6">
        @csrf
        <div>
            <label for="install_key" class="field-label">Install key</label>
            <input id="install_key" name="install_key" type="password" class="field" required autocomplete="off">
            <p class="field-hint">The INSTALL_KEY value from your .env file.</p>
            @error('install_key') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="grid gap-5 sm:grid-cols-2">
            <legend class="mb-4 font-display text-2xl font-extrabold text-abyss-900 sm:col-span-2">First admin account</legend>
            <div class="sm:col-span-2">
                <label for="full_name" class="field-label">Full name</label>
                <input id="full_name" name="full_name" value="{{ old('full_name') }}" class="field" required>
                @error('full_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="matric_no" class="field-label">Matric / staff number</label>
                <input id="matric_no" name="matric_no" value="{{ old('matric_no') }}" class="field uppercase" required>
                @error('matric_no') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone_number" class="field-label">Phone</label>
                <input id="phone_number" name="phone_number" value="{{ old('phone_number') }}" class="field" required>
                @error('phone_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="field" required>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" class="field" autocomplete="new-password" required>
                <p class="field-hint">At least 10 characters, letters and numbers.</p>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Type it again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required>
            </div>
        </fieldset>

        <label class="flex items-start gap-3 rounded-lg border border-glass-200 bg-white p-4 text-sm">
            <input type="checkbox" name="seed_demo" value="1" class="mt-0.5 size-4 accent-abyss-900">
            <span><span class="font-bold">Add demo data</span><span class="block text-ink-soft">Sample MT, members and events for trying things out. Leave unticked for the real launch.</span></span>
        </label>

        <button type="submit" class="btn-dark w-full py-3 text-base" @disabled($dbError)>Install</button>
    </form>
</x-layouts.auth>
