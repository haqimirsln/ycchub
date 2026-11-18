<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\User;
use App\Models\ProgramFeedback;
use App\Services\ActivityStatusService;
use App\Services\MeritService;
use App\Services\VolunteerHoursService;
use App\Support\DatabaseStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(Request $request, ActivityStatusService $activity, VolunteerHoursService $hours, MeritService $merit): View
    {
        $user = $request->user();

        // Keep the badge honest even if the nightly job hasn't run today.
        if (! $user->activity_checked_at || $user->activity_checked_at->lt(now()->startOfDay())) {
            $activity->refreshUser($user);
        }

        $myRegs = $user->registrations()->with('program')->get()->keyBy('program_id');

        $upcoming = Program::query()
            ->whereIn('status', ['published', 'cancelled'])
            ->upcoming()
            ->withSeatCounts()
            ->get()
            // Cancelled events only matter to members who had registered.
            ->filter(fn (Program $p) => $p->status === 'published' || $myRegs->has($p->id))
            ->values();

        $history = $user->registrations()
            ->with('program')
            ->whereHas('program', fn ($q) => $q->where('starts_at', '<', now()->startOfDay()))
            ->whereIn('status', ['attended', 'absent', 'registered'])
            ->get()
            ->sortByDesc(fn ($r) => $r->program->starts_at)
            ->values();

        $daysSince = $user->last_attended_on
            ? $activity->countedDaysBetween($user->last_attended_on, now())
            : null;

        // Events they attended recently and haven't rated yet.
        $toRate = collect();
        if (DatabaseStatus::feedbackReady()) {
            $rated = ProgramFeedback::query()->where('user_id', $user->id)->pluck('program_id')->all();
            $toRate = $myRegs
                ->filter(fn ($r) => $r->status === 'attended' && $r->program && $r->program->isFeedbackOpen() && ! in_array($r->program_id, $rated, true))
                ->sortByDesc(fn ($r) => $r->program->starts_at)
                ->take(3)
                ->values();
        }

        // Excos and admins get a short to-do list on top of their own hub.
        $staff = null;
        if ($user->isExco()) {
            $staff = [
                'pending' => User::query()->pending()->count(),
                'attendance' => Program::query()->published()
                    ->where('starts_at', '<', now())
                    ->where('starts_at', '>=', now()->subDays((int) config('ycc.attendance_lock_days', 14)))
                    ->whereHas('registrations', fn ($q) => $q->where('status', 'registered'))
                    ->count(),
                'drafts' => Program::query()->where('status', 'draft')->count(),
                'database' => $user->isAdmin() && DatabaseStatus::pending(),
            ];
        }

        return view('portal.index', [
            'user' => $user,
            'upcoming' => $upcoming,
            'myRegs' => $myRegs,
            'history' => $history,
            'daysSince' => $daysSince,
            'rules' => config('ycc.activity'),
            'hours' => $hours->forUser($user),
            'toRate' => $toRate,
            'staff' => $staff,
            'merit' => $user->earnsMerit() ? $merit->summary($user) : null,
            'club' => $this->clubStats(),
            'leader' => app(\App\Services\MtService::class)->leaderOf($user->team),
            'leads' => ($ledTeam = app(\App\Services\MtService::class)->teamLedBy($user))
                ? ['team' => $ledTeam, 'count' => \App\Models\User::query()->tracked()->where('team', $ledTeam)->count()]
                : null,
        ]);
    }

    /** Club numbers every member sees on My hub, to show the club is alive (and nudge the quiet ones). */
    private function clubStats(): array
    {
        $counts = User::query()->tracked()->selectRaw('activity_status, COUNT(*) as total')->groupBy('activity_status')->pluck('total', 'activity_status');
        $total = (int) $counts->sum();
        $statuses = [];
        foreach (['active', 'at_risk', 'inactive', 'new'] as $s) {
            $n = (int) ($counts[$s] ?? 0);
            $statuses[$s] = ['count' => $n, 'pct' => $total ? (int) round($n / $total * 100) : 0];
        }

        return ['total' => $total, 'statuses' => $statuses];
    }
}
