<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use App\Support\MemberRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'faculties' => config('ycc.faculties'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone_number' => preg_replace('/[^\d+]/', '', (string) $request->input('phone_number')),
        ]);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
            ...MemberRules::studies(! $user->isAdvisor(), $user->isAdvisor() || $user->member_type === 'staff'),
        ], [
            'phone_number.regex' => 'Enter a valid phone number, e.g. 0123456789.',
            ...MemberRules::messages(),
        ]);
        $data = MemberRules::clean($data);
        $teamBefore = $user->team;

        // Matric number is the member's identity in the club, so only an admin changes it.
        $user->fill($data);
        if ($request->has('email_news') && \App\Support\DatabaseStatus::hasColumn('users', 'email_opt_out')) {
            $user->email_opt_out = ! $request->boolean('email_news');
        }
        $user->save();
        if ($teamBefore !== $user->team) {
            Activity::log('member.team', "{$user->full_name} ".($teamBefore ? 'moved to' : 'joined')." the {$user->teamName()}", $user);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->forceFill(['password' => $request->input('password')])->save();

        return back()->with('success', 'Password changed.');
    }

    public function photo(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.max' => 'Photo must be 5 MB or smaller.',
            'photo.mimes' => 'Use a JPG, PNG or WebP photo.',
            'photo.image' => 'That file is not a photo.',
        ]);

        $user = $request->user();
        $jpeg = $this->squareJpeg($request->file('photo')->getRealPath());
        if ($jpeg === null) {
            return back()->withErrors(['photo' => 'We could not read that photo. Try a different one.']);
        }

        Storage::disk('uploads')->put($user->avatarPath(), $jpeg);

        return back()->with('success', 'Profile picture updated.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        Storage::disk('uploads')->delete($request->user()->avatarPath());

        return back()->with('success', 'Profile picture removed.');
    }

    /**
     * Centre-crops the photo to a 400 x 400 JPEG so every avatar is small and the same shape.
     * Falls back to the original file if GD is missing on the server.
     */
    private function squareJpeg(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return file_get_contents($path) ?: null;
        }

        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            return null;
        }

        // Phone photos are often stored sideways with a rotation flag; apply it.
        if (function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $src = imagerotate($src, $angle, 0) ?: $src;
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $size = min(400, $side);

        $dst = imagecreatetruecolor($size, $size);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparent PNGs onto white
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        ob_start();
        imagejpeg($dst, null, 85);

        return ob_get_clean() ?: null;
    }
}
