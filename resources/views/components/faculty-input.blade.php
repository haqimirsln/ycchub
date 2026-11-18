@props(['value' => null])
{{-- Faculty is typed in (UMT renames faculties), with the current names suggested. --}}
<div>
    <label for="faculty" class="field-label">Faculty / centre</label>
    <input id="faculty" name="faculty" value="{{ $value }}" list="faculty-list" maxlength="120" autocomplete="off" class="field" placeholder="Start typing, e.g. Faculty of Science and Marine Environment">
    <datalist id="faculty-list">
        @foreach (config('ycc.faculty_suggestions') as $name)
            <option value="{{ $name }}"></option>
        @endforeach
    </datalist>
    <p class="field-hint">Pick from the list or type it if yours isn't there.</p>
    @error('faculty') <p class="field-error">{{ $message }}</p> @enderror
</div>
