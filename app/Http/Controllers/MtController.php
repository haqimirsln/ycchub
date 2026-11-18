<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\MtService;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Our MT": who runs YCC this session and who to message about what. */
class MtController extends Controller
{
    public function index(MtService $mt): View
    {
        return view('mt.index', [
            'ready' => $mt->ready(),
            'session' => $mt->session(),
            'advisor' => $mt->advisor(),
            'advisorUser' => \App\Models\User::query()->approved()->where('role', 'advisor')->orderBy('id')->first(),
            'terms' => $mt->current(),
            'past' => $mt->past(),
            'isPublic' => $mt->isPublic(),
        ]);
    }

    /** Superadmin: advisor name and whether the MT shows on the public home page. */
    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mt_advisor' => ['nullable', 'string', 'max:150'],
            'mt_public' => ['nullable', 'boolean'],
        ]);
        Setting::put('mt_advisor', trim((string) ($data['mt_advisor'] ?? '')) ?: null);
        Setting::put('mt_public', $request->boolean('mt_public') ? '1' : '0');
        Activity::log('system.settings', 'Updated the Our MT page settings');

        return back()->with('success', 'Saved.');
    }

    /** Superadmin: handover to a new session. */
    public function session(Request $request, MtService $mt): RedirectResponse
    {
        $data = $request->validate([
            'session' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'demote' => ['nullable', 'boolean'],
        ], [
            'session.regex' => 'Write the session like 2027/2028.',
            'session.different' => 'That is already the current session.',
        ]);
        $old = $mt->session();
        if ($data['session'] === $old) {
            return back()->withErrors(['session' => 'That is already the current session.']);
        }
        $demoted = $mt->startSession($data['session'], $request->boolean('demote'));

        return back()->with('success', "Session {$data['session']} started. The {$old} MT is now under Past MT"
            .($demoted ? " and {$demoted} of them are members again." : '.').' Give the new MT their positions in Roles & access.');
    }
}
