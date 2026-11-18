<x-layouts.auth title="Choose a new password" heading="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="field" autocomplete="email" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="field-label">New password</label>
            <input id="password" name="password" type="password" class="field" autocomplete="new-password" required>
            <p class="field-hint">At least 8 characters, with letters and numbers.</p>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Type it again</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn-dark w-full py-3 text-base">Save new password</button>
    </form>
</x-layouts.auth>
