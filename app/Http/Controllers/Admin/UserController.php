<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityStatusService;
use App\Services\MtService;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Roles & access. Superadmin only: roles, suspending and deleting accounts. */
class UserController extends Controller
{
    public function index(Request $request, MtService $mt): View
    {
        $role = in_array($request->query('role'), User::ROLES, true) ? $request->query('role') : '';
        $state = in_array($request->query('state'), ['approved', 'suspended', 'pending', 'rejected'], true) ? $request->query('state') : '';
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($role !== '', fn ($w) => $w->where('role', $role))
            ->when($state !== '', fn ($w) => $w->where('account_status', $state))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $w->where(fn ($x) => $x->where('full_name', 'like', $like)->orWhere('matric_no', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderByRaw("CASE role WHEN 'superadmin' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('full_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'filters' => compact('role', 'state', 'q'),
            'roleCounts' => User::query()->approved()->selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role'),
            'mtReady' => $mt->ready(),
            'session' => $mt->ready() ? $mt->session() : '',
            'holders' => $mt->current()->filter(fn ($t) => $t->exists)->keyBy('position'),
        ]);
    }

    public function role(Request $request, User $user, ActivityStatusService $activity): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);

        if ($user->is($request->user())) {
            return back()->with('error', "You can't change your own role.");
        }
        if ($user->isSuperadmin() && $data['role'] !== 'superadmin' && User::query()->approved()->where('role', 'superadmin')->count() <= 1) {
            return back()->with('error', 'There must be at least one superadmin.');
        }
        if (! $user->isApproved()) {
            return back()->with('error', 'Approve this account before giving it a role.');
        }

        $before = $user->roleLabel();
        $wasAdvisor = $user->isAdvisor();
        $user->forceFill(['role' => $data['role']])->save();
        if ($user->isAdvisor()) {
            // Advisors are staff, not students: no position, team, status or merit.
            app(MtService::class)->assign($user, null);
            $user->forceFill(['member_type' => 'staff', 'team' => null])->save();
        } elseif ($wasAdvisor && $user->member_type === 'staff') {
            $user->forceFill(['member_type' => 'student'])->save();
        }
        $activity->refreshUser($user);
        Activity::log('member.role', "Changed {$user->full_name} from {$before} to {$user->roleLabel()}", $user);

        return back()->with('success', "{$user->full_name} is now {$user->roleLabel()}.");
    }

    /** Gives or removes an MT position (jawatan) for this session. Holding one makes the account MT. */
    public function position(Request $request, User $user, MtService $mt, ActivityStatusService $activity): RedirectResponse
    {
        $data = $request->validate(['position' => ['nullable', Rule::in(array_keys(config('ycc.positions')))]]);
        if (! $user->isApproved()) {
            return back()->with('error', 'Approve this account before giving it a position.');
        }
        if ($user->isAdvisor()) {
            return back()->with('error', 'Advisors can\'t hold an MT position. The Penasihat is set on the Our MT page.');
        }

        $before = $user->positionLabel();
        $position = $data['position'] ?? null;
        $previousHolder = $position ? \App\Models\MtTerm::query()->with('user')->where('session', $mt->session())->where('position', $position)->first()?->user : null;
        $mt->assign($user, $position);
        $activity->refreshUser($user->fresh());

        if (! $position) {
            Activity::log('member.role', "Removed {$user->full_name} as {$before} ({$mt->session()})", $user);

            return back()->with('success', "{$user->full_name} no longer holds a position. Their role is still {$user->fresh()->roleLabel()}; change it if they should go back to member.");
        }

        $label = config("ycc.positions.$position.label");
        Activity::log('member.role', "Made {$user->full_name} {$label} for {$mt->session()}", $user);
        $note = $previousHolder && ! $previousHolder->is($user) ? " {$previousHolder->full_name} no longer holds it." : '';

        return back()->with('success', "{$user->full_name} is now {$label}.{$note}");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', "You can't suspend yourself.");
        }
        if ($user->isSuperadmin()) {
            return back()->with('error', 'Change this superadmin to member first before suspending.');
        }

        $user->forceFill(['account_status' => 'suspended', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
        Activity::log('member.suspended', "Suspended {$user->full_name} ({$user->matric_no})", $user);

        return back()->with('success', "{$user->full_name} suspended. They can no longer log in.");
    }

    public function reinstate(Request $request, User $user, ActivityStatusService $activity): RedirectResponse
    {
        if ($user->account_status !== 'suspended') {
            return back()->with('error', 'This account is not suspended.');
        }

        $user->forceFill(['account_status' => 'approved', 'reviewed_by' => $request->user()->id])->save();
        $activity->refreshUser($user);
        Activity::log('member.reinstated', "Reinstated {$user->full_name} ({$user->matric_no})", $user);

        return back()->with('success', "{$user->full_name} reinstated.");
    }

    /** Permanently removes an account with its registrations, merit and feedback. Superadmin only. */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', "You can't delete your own account.");
        }
        if ($user->isSuperadmin()) {
            return back()->with('error', 'Change this superadmin to member first before deleting.');
        }

        $label = "{$user->full_name} ({$user->matric_no})";
        Storage::disk('uploads')->delete($user->avatarPath());
        $user->delete();
        Activity::log('member.deleted', "Deleted account {$label}");

        return back()->with('success', "{$label} deleted.");
    }
}
