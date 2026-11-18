<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use App\Support\MemberRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'matric_no' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('matric_no'))),
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone_number' => preg_replace('/[^\d+]/', '', (string) $request->input('phone_number')),
        ]);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'matric_no' => ['required', 'string', 'regex:/^[A-Z0-9]{5,20}$/', Rule::unique('users', 'matric_no')],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone_number' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
            ...MemberRules::studies(),
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'pdpa_consent' => ['accepted'],
        ], [
            'matric_no.regex' => 'Matric number should be letters and digits only (e.g. S12345).',
            'matric_no.unique' => 'This matric number is already registered. Try logging in instead.',
            'email.unique' => 'This email is already registered. Try logging in instead.',
            'phone_number.regex' => 'Enter a valid phone number, e.g. 0123456789.',
            'pdpa_consent.accepted' => 'Please agree to the personal data notice to continue.',
            ...MemberRules::messages(),
        ]);
        $data = MemberRules::clean($data);

        $user = User::create([
            ...collect($data)->except(['pdpa_consent', 'password_confirmation'])->all(),
            'pdpa_consent_at' => now(),
        ]);

        Activity::log('member.registered', "{$user->full_name} ({$user->matric_no}) signed up".($user->teamName() ? " for the {$user->teamName()}" : '').($user->isAlumni() ? ' as alumni' : ''), $user, $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.pending');
    }
}
