<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $counts = User::query()->tracked()
            ->selectRaw('activity_status, COUNT(*) as total')
            ->groupBy('activity_status')
            ->pluck('total', 'activity_status');

        $statuses = ['active', 'at_risk', 'inactive', 'new'];
        $total = (int) $counts->sum();
        $distribution = collect($statuses)->mapWithKeys(fn ($s) => [$s => [
            'count' => (int) ($counts[$s] ?? 0),
            'percent' => $total ? round(($counts[$s] ?? 0) / $total * 100, 1) : 0,
        ]]);

        $recentPrograms = Program::query()->published()->past()->withSeatCounts()->limit(6)->get();

        $attention = User::query()->tracked()
            ->whereIn('activity_status', ['at_risk', 'inactive'])
            ->orderByRaw("CASE activity_status WHEN 'at_risk' THEN 0 ELSE 1 END")
            ->orderBy('last_attended_on')
            ->limit(8)
            ->get();

        $upcoming = Program::query()->whereIn('status', ['published', 'draft'])->upcoming()->withSeatCounts()->limit(4)->get();

        $needsAttendance = Program::query()->published()
            ->where('starts_at', '<', now())
            ->where('starts_at', '>=', now()->subDays((int) config('ycc.attendance_lock_days', 14)))
            ->whereHas('registrations', fn ($q) => $q->where('status', 'registered'))
            ->withCount(['registrations as unmarked_count' => fn ($q) => $q->where('status', 'registered')])
            ->orderByDesc('starts_at')
            ->get();

        return view('exco.dashboard', [
            'total' => $total,
            'distribution' => $distribution,
            'pendingCount' => User::query()->pending()->count(),
            'programCount' => Program::query()->published()->count(),
            'recentPrograms' => $recentPrograms,
            'attention' => $attention,
            'upcoming' => $upcoming,
            'needsAttendance' => $needsAttendance,
        ]);
    }
}
