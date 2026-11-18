@props(['selected' => null, 'allowNone' => true])
{{-- Faculty as tappable cards instead of a dropdown, so full names are readable on any phone. --}}
<fieldset>
    <legend class="field-label">Faculty</legend>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach (config('ycc.faculties') as $code => $name)
            <label class="choice-card">
                <input type="radio" name="faculty" value="{{ $code }}" class="sr-only" @checked($selected === $code)>
                <span class="choice-dot" aria-hidden="true"></span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold text-ink">{{ $code }}</span>
                    <span class="block text-sm leading-snug text-ink-soft">{{ \Illuminate\Support\Str::after($name, 'Faculty of ') }}</span>
                </span>
            </label>
        @endforeach
        @if ($allowNone)
            <label class="choice-card">
                <input type="radio" name="faculty" value="" class="sr-only" @checked(blank($selected))>
                <span class="choice-dot" aria-hidden="true"></span>
                <span class="text-sm text-ink-soft">Skip for now</span>
            </label>
        @endif
    </div>
    @error('faculty') <p class="field-error">{{ $message }}</p> @enderror
</fieldset>
