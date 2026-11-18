<x-layouts.auth title="Log in" heading="Welcome back">
    <p class="mt-2 text-ink-soft">Log in with your matric number or email.</p>

    @if (request()->boolean('installed'))
        <div class="mt-6 rounded-lg border border-st-active/30 bg-white p-4 text-sm">
            <p class="font-bold text-st-active">YCC Hub is installed.</p>
            <p class="mt-1 text-ink-soft">Log in with the admin account you just created. Then delete the INSTALL_KEY line from .env.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="login" class="field-label">Matric number or email</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" class="field" autocomplete="username" autocapitalize="characters" required autofocus>
            @error('login') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="field-label">Password</label>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-sm text-kelp-500 underline-offset-4 hover:underline">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" class="field" autocomplete="current-password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-glass-300 accent-abyss-900"> Keep me logged in on this device
        </label>
        <button type="submit" class="btn-dark w-full py-3 text-base">Log in</button>
    </form>

    <p class="mt-8 text-center text-ink-soft">
        New to YCC? <a href="{{ route('register') }}" class="font-bold text-abyss-900 underline decoration-crest-500 decoration-2 underline-offset-4">Join the club</a>
    </p>
</x-layouts.auth>
