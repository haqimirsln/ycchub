<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\MeritEntry;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** MT gives merit (achievements) or demerit (discipline) by hand, always with a reason. */
class MeritController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->earnsMerit(), 422, 'Only normal members earn merit and demerit.');

        $data = $request->validate([
            'kind' => ['required', 'in:merit,demerit'],
            'points' => ['required', 'integer', 'between:1,100'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], ['reason.required' => 'Write the reason, so the member knows what it was for.']);

        $points = $data['kind'] === 'demerit' ? -$data['points'] : (int) $data['points'];
        MeritEntry::query()->create([
            'user_id' => $user->id,
            'points' => $points,
            'source' => 'manual',
            'reason' => trim($data['reason']),
            'awarded_by' => $request->user()->id,
        ]);

        $word = $points < 0 ? 'demerit' : 'merit';
        Activity::log('merit.given', "Gave {$user->full_name} ".abs($points)." {$word}: {$data['reason']}", $user);

        return back()->with('success', abs($points)." {$word} ".($points < 0 ? 'given to' : 'awarded to')." {$user->firstName()}.");
    }

    public function destroy(Request $request, MeritEntry $entry): RedirectResponse
    {
        abort_unless($entry->isManual(), 403, 'Automatic merit follows attendance. Change the attendance instead.');

        $user = $entry->user;
        $entry->delete();
        Activity::log('merit.removed', "Removed {$entry->points} points from {$user?->full_name}: {$entry->reason}", $user);

        return back()->with('success', 'Entry removed.');
    }
}
