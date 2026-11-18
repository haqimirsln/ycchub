<?php

namespace App\Services;

use App\Models\ActivityPause;
use App\Models\ProgramRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Single source of truth for member activity status.
 *
 * The result is cached on users.activity_status so every screen (dashboard,
 * member badge, CSV export) reads the same value. It is recalculated:
 *  - for one member whenever their attendance changes,
 *  - for everyone nightly by the scheduler (because time passing changes status),
 *  - for everyone when an admin edits activity pause periods.
 */
class ActivityStatusService
{
    /** @var array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>|null merged pause ranges */
    private ?array $pauses = null;

    public function classify(?CarbonInterface $lastAttendedOn, CarbonInterface $joinedOn, int $totalAttended, ?CarbonInterface $today = null): string
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $rules = config('ycc.activity');

        if ($totalAttended === 0 || $lastAttendedOn === null) {
            $sinceJoined = $this->countedDaysBetween($joinedOn, $today);

            return $sinceJoined <= $rules['grace_days'] ? 'new' : 'inactive';
        }

        $since = $this->countedDaysBetween($lastAttendedOn, $today);

        return match (true) {
            $since <= $rules['active_days'] => 'active',
            $since <= $rules['at_risk_days'] => 'at_risk',
            default => 'inactive',
        };
    }

    /**
     * Calendar days from $from to $to, minus days inside activity pauses.
     * Counts the days after $from up to and including $to.
     */
    public function countedDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        $total = (int) $from->diffInDays($to);
        $windowStart = $from->addDay();

        foreach ($this->pauses() as [$pStart, $pEnd]) {
            $start = $pStart->greaterThan($windowStart) ? $pStart : $windowStart;
            $end = $pEnd->lessThan($to) ? $pEnd : $to;
            if ($end->greaterThanOrEqualTo($start)) {
                $total -= ((int) $start->diffInDays($end)) + 1;
            }
        }

        return max(0, $total);
    }

    /** Recalculate one member (call after their attendance changes). */
    public function refreshUser(User $user): User
    {
        $stats = $this->attendanceStats([$user->id])->get($user->id);

        return $this->apply($user, $stats);
    }

    /**
     * Recalculate every tracked member (or only the given ids).
     *
     * @param  array<int>|null  $userIds
     * @return array<string, int> counts per status
     */
    public function refreshAll(?array $userIds = null): array
    {
        $this->pauses = null; // pick up any pause changes
        app(MeritService::class)->resetCache();
        $counts = ['new' => 0, 'active' => 0, 'at_risk' => 0, 'inactive' => 0];

        User::query()->tracked()
            ->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds))
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$counts) {
                $stats = $this->attendanceStats($users->pluck('id')->all());
                foreach ($users as $user) {
                    $this->apply($user, $stats->get($user->id));
                    $counts[$user->activity_status]++;
                }
            });

        return $counts;
    }

    /**
     * Last attended date and total attendances per user, counting only events
     * that are not deleted, not cancelled, and marked as counting toward activity.
     *
     * @param  array<int>  $userIds
     * @return Collection<int, object{user_id:int,last_at:string,total:int}>
     */
    private function attendanceStats(array $userIds): Collection
    {
        return ProgramRegistration::query()
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->whereIn('program_registrations.user_id', $userIds)
            ->where('program_registrations.status', 'attended')
            ->where('programs.counts_for_activity', true)
            ->where('programs.status', '!=', 'cancelled')
            ->whereNull('programs.deleted_at')
            ->groupBy('program_registrations.user_id')
            ->selectRaw('program_registrations.user_id as user_id, MAX(programs.starts_at) as last_at, COUNT(*) as total')
            ->toBase()
            ->get()
            ->keyBy('user_id');
    }

    private function apply(User $user, ?object $stats): User
    {
        $last = $stats?->last_at ? CarbonImmutable::parse($stats->last_at)->startOfDay() : null;
        $total = (int) ($stats->total ?? 0);
        $joined = $user->reviewed_at ?? $user->created_at ?? now();

        $user->forceFill([
            'last_attended_on' => $last?->toDateString(),
            'total_attended' => $total,
            // The superadmin and advisors keep whatever was stored; it's never shown or counted for them.
            'activity_status' => $user->isApproved() && $user->hasActivityStatus()
                ? $this->classify($last, $joined, $total)
                : $user->activity_status,
            'activity_checked_at' => now(),
        ])->saveQuietly();

        // Merit follows attendance, so it is recalculated at the same moments.
        app(MeritService::class)->syncUser($user);

        return $user;
    }

    /** @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}> */
    private function pauses(): array
    {
        if ($this->pauses !== null) {
            return $this->pauses;
        }

        $ranges = ActivityPause::query()->orderBy('starts_on')->get()
            ->map(fn (ActivityPause $p) => [
                CarbonImmutable::parse($p->starts_on)->startOfDay(),
                CarbonImmutable::parse($p->ends_on)->startOfDay(),
            ])
            ->filter(fn ($r) => $r[1]->greaterThanOrEqualTo($r[0]))
            ->values()->all();

        // Merge overlapping ranges so overlapping pauses are not subtracted twice.
        $merged = [];
        foreach ($ranges as $range) {
            $lastIdx = count($merged) - 1;
            if ($lastIdx >= 0 && $range[0]->lessThanOrEqualTo($merged[$lastIdx][1]->addDay())) {
                if ($range[1]->greaterThan($merged[$lastIdx][1])) {
                    $merged[$lastIdx][1] = $range[1];
                }
            } else {
                $merged[] = $range;
            }
        }

        return $this->pauses = $merged;
    }
}
