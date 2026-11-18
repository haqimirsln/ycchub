<x-layouts.auth title="Reset password" heading="Forgot your password?">
    <p class="mt-2 text-ink-soft">Enter the email you signed up with and we'll send a reset link.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="email" class="field-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="field" autocomplete="email" required autofocus>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-dark w-full py-3 text-base">Send reset link</button>
    </form>

    <p class="mt-8 text-center text-ink-soft"><a href="{{ route('login') }}" class="font-bold text-abyss-900 underline decoration-crest-500 decoration-2 underline-offset-4">Back to log in</a></p>
</x-layouts.auth>
