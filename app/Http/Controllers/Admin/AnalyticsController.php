<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\ActivityStatusService;
use App\Services\VolunteerHoursService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admin analytics: every club member listed under their activity status,
 * plus breakdowns by faculty and year, a 12-month attendance trend, members
 * about to slip from Active to At-Risk, and the most engaged members.
 */
class AnalyticsController extends Controller
{
    public const STATUSES = ['active', 'at_risk', 'inactive', 'new'];

    /** Active members this many days (or fewer) from turning at-risk are flagged as slipping. */
    private const SLIP_WINDOW = 14;

    public function index(Request $request, ActivityStatusService $activity, VolunteerHoursService $hours, \App\Services\MeritService $merit): View
    {
        $faculties = config('ycc.faculties');
        $filters = [
            'faculty' => mb_substr((string) $request->query('faculty', ''), 0, 120),
            'team' => array_key_exists((string) $request->query('team'), config('ycc.teams')) || $request->query('team') === 'none' ? (string) $request->query('team') : '',
            'year' => in_array($request->query('year'), ['1', '2', '3', '4', '5', 'none'], true) ? (string) $request->query('year') : '',
            'role' => in_array($request->query('role'), ['member', 'admin'], true) ? (string) $request->query('role') : '',
        ];

        $members = User::query()->tracked()
            ->when($filters['faculty'] === 'none', fn ($q) => $q->whereNull('faculty'))
            ->when($filters['faculty'] !== '' && $filters['faculty'] !== 'none', fn ($q) => $q->where('faculty', $filters['faculty']))
            ->when($filters['team'] === 'none', fn ($q) => $q->whereNull('team'))
            ->when($filters['team'] !== '' && $filters['team'] !== 'none', fn ($q) => $q->where('team', $filters['team']))
            ->when($filters['year'] === 'none', fn ($q) => $q->whereNull('year_of_study'))
            ->when($filters['year'] !== '' && $filters['year'] !== 'none', fn ($q) => $q->where('year_of_study', (int) $filters['year']))
            ->when($filters['role'] !== '', fn ($q) => $q->where('role', $filters['role']))
            ->withCount(['registrations as no_show_count' => fn ($q) => $q->where('status', 'absent')])
            ->orderBy('full_name')
            ->get();

        $rules = config('ycc.activity');
        $today = now()->startOfDay();

        // Counted days since last attendance (or since joining, for members who never attended).
        $members->each(function (User $m) use ($activity, $today, $rules) {
            $from = $m->last_attended_on ?? ($m->reviewed_at ?? $m->created_at);
            $m->days_since = $activity->countedDaysBetween($from, $today);
            $m->days_to_risk = $m->activity_status === 'active' ? max(0, $rules['active_days'] - $m->days_since + 1) : null;
        });

        $byStatus = collect(self::STATUSES)->mapWithKeys(fn ($s) => [
            $s => $members->where('activity_status', $s)->sortByDesc('days_since')->values(),
        ]);

        $slipping = $byStatus['active']
            ->filter(fn (User $m) => $m->days_to_risk <= self::SLIP_WINDOW)
            ->sortBy('days_to_risk')
            ->values();

        return view('admin.analytics', [
            'filters' => $filters,
            'faculties' => $faculties,
            'total' => $members->count(),
            'byStatus' => $byStatus,
            'slipping' => $slipping,
            'slipWindow' => self::SLIP_WINDOW,
            'facultyRows' => $this->breakdown($members, 'faculty', $members->pluck('faculty')->filter()->countBy()->sortDesc()->keys()->all()),
            'teamRows' => $this->breakdown($members, 'team', array_keys(config('ycc.teams'))),
            'yearRows' => $this->breakdown($members, 'year_of_study', [1, 2, 3, 4, 5]),
            'trend' => $this->monthlyTrend($members->pluck('id')->all()),
            'topMembers' => $members->sortByDesc('total_attended')->filter(fn ($m) => $m->total_attended > 0)->take(10)->values(),
            'hoursByUser' => $hoursByUser = $hours->byUser(now()->year, $members->pluck('id')->all()),
            'topVolunteers' => $hoursByUser->take(10)->map(fn ($h, $id) => ['user' => $members->firstWhere('id', $id), 'hours' => $h])->filter(fn ($row) => $row['user'])->values(),
            'topMerit' => $merit->totals($members->filter->earnsMerit()->pluck('id')->all())
                ->filter(fn ($pts) => $pts > 0)->take(10)
                ->map(fn ($pts, $id) => ['user' => $members->firstWhere('id', $id), 'points' => $pts])
                ->filter(fn ($row) => $row['user'])->values(),
            'rules' => $rules,
        ]);
    }

    /**
     * Status counts grouped by a column, in a fixed order, with a "Not set" row at the end.
     *
     * @return Collection<int, array{key: string, label: string, total: int, counts: array<string,int>, active_rate: int}>
     */
    private function breakdown(Collection $members, string $column, array $order): Collection
    {
        $groups = $members->groupBy(fn (User $m) => $m->{$column} === null ? 'none' : (string) $m->{$column});

        return collect(array_map('strval', $order))
            ->push('none')
            ->map(function (string $key) use ($groups, $column) {
                $group = $groups->get($key, collect());
                $counts = collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => $group->where('activity_status', $s)->count()])->all();
                $engaged = $counts['active'] + $counts['at_risk'] + $counts['inactive'];

                return [
                    'key' => $key,
                    'label' => match (true) {
                        $key === 'none' => 'Not set',
                        $column === 'year_of_study' => 'Year '.$key,
                        $column === 'team' => config('ycc.teams.'.$key, $key),
                        $column === 'faculty' => config('ycc.faculties.'.$key, $key),
                        default => $key,
                    },
                    'name' => null,
                    'total' => $group->count(),
                    'counts' => $counts,
                    // Share of members past their grace period who are active.
                    'active_rate' => $engaged ? (int) round($counts['active'] / $engaged * 100) : 0,
                ];
            })
            ->filter(fn ($row) => $row['total'] > 0 || $row['key'] !== 'none')
            ->values();
    }

    /**
     * Attendances and unique members per month for the last 12 months.
     * Done in PHP so it works the same on MySQL and SQLite.
     *
     * @param  array<int>  $userIds
     */
    private function monthlyTrend(array $userIds): Collection
    {
        $start = now()->startOfMonth()->subMonths(11);

        $rows = ProgramRegistration::query()
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->whereIn('program_registrations.user_id', $userIds)
            ->where('program_registrations.status', 'attended')
            ->where('programs.counts_for_activity', true)
            ->where('programs.status', '!=', 'cancelled')
            ->whereNull('programs.deleted_at')
            ->where('programs.starts_at', '>=', $start)
            ->toBase()
            ->get(['program_registrations.user_id', 'programs.starts_at', 'programs.id as program_id']);

        $byMonth = $rows->groupBy(fn ($r) => Carbon::parse($r->starts_at)->format('Y-m'));

        return collect(range(0, 11))->map(function (int $i) use ($start, $byMonth) {
            $month = $start->copy()->addMonths($i);
            $items = $byMonth->get($month->format('Y-m'), collect());

            return [
                'label' => $month->format('M'),
                'full' => $month->format('F Y'),
                'attendances' => $items->count(),
                'members' => $items->pluck('user_id')->unique()->count(),
                'programs' => $items->pluck('program_id')->unique()->count(),
            ];
        });
    }
}
