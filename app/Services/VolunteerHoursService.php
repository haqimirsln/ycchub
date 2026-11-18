<?php

namespace App\Services;

use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Volunteer hours: the hours of every program a member attended. Cancelled
 * programs and ones that don't count toward activity (AGMs, exco meetings)
 * are left out.
 */
class VolunteerHoursService
{
    /** 26.0 -> "26", 3.5 -> "3.5" */
    public static function format(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 1, '.', ','), '0'), '.');
    }

    /** @return array{year: float, total: float, programs_year: int} */
    public function forUser(User $user, ?int $year = null): array
    {
        $year ??= now()->year;
        $rows = $this->attended([$user->id]);
        $inYear = $rows->filter(fn ($r) => $r->program->starts_at->year === $year);

        return [
            'year' => round($inYear->sum(fn ($r) => $r->program->volunteerHours()), 1),
            'total' => round($rows->sum(fn ($r) => $r->program->volunteerHours()), 1),
            'programs_year' => $inYear->count(),
        ];
    }

    /**
     * Hours per user in a year, highest first.
     *
     * @param  list<int>|null  $userIds  limit to these members (null = everyone)
     * @return Collection<int, float> user id => hours
     */
    public function byUser(?int $year = null, ?array $userIds = null): Collection
    {
        $year ??= now()->year;

        return $this->attended($userIds)
            ->filter(fn ($r) => $r->program->starts_at->year === $year)
            ->groupBy('user_id')
            ->map(fn ($rows) => round($rows->sum(fn ($r) => $r->program->volunteerHours()), 1))
            ->filter(fn ($h) => $h > 0)
            ->sortDesc();
    }

    private function attended(?array $userIds): Collection
    {
        return ProgramRegistration::query()
            ->where('status', 'attended')
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->whereHas('program', fn ($q) => $q->where('status', '!=', 'cancelled')->where('counts_for_activity', true))
            ->with('program')
            ->get(['id', 'user_id', 'program_id']);
    }
}
