@props(['selected' => null, 'required' => true])
{{-- YCC's four teams as tappable cards. --}}
@php $blurbs = config('ycc.team_blurbs'); @endphp
<fieldset>
    <legend class="field-label">Team @unless ($required)<span class="font-normal text-ink-soft">(optional)</span>@endunless</legend>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach (config('ycc.teams') as $key => $name)
            <label class="choice-card">
                <input type="radio" name="team" value="{{ $key }}" class="sr-only" @checked($selected === $key) @required($required)>
                <span class="choice-dot" aria-hidden="true"></span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold text-ink">{{ $name }}</span>
                    <span class="block text-sm leading-snug text-ink-soft">{{ $blurbs[$key] ?? '' }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('team') <p class="field-error">{{ $message }}</p> @enderror
</fieldset>
