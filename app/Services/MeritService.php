<?php

namespace App\Services;

use App\Models\ActivityPause;
use App\Models\MeritEntry;
use App\Models\ProgramCommittee;
use App\Models\ProgramRegistration;
use App\Models\Setting;
use App\Models\User;
use App\Support\DatabaseStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Merit and demerit for normal members.
 *
 * Automatic entries (attendance, semester-break bonus, milestones, AJK, no-shows)
 * are recalculated from the member's record whenever their attendance changes,
 * so correcting attendance corrects merit too. Entries given by the MT by hand
 * are never touched by the recalculation.
 */
class MeritService
{
    /** @var array<int, array{0: Carbon, 1: Carbon}>|null */
    private ?array $pauses = null;

    /** @return array{attend:int, break_bonus:int, committee:int, no_show:int, milestones:array<int,int>} */
    public function rules(): array
    {
        $defaults = config('ycc.merit');
        $saved = json_decode((string) Setting::get('merit_rules', ''), true);
        $rules = is_array($saved) ? array_merge($defaults, $saved) : $defaults;

        $milestones = collect($rules['milestones'] ?? [])
            ->mapWithKeys(fn ($bonus, $count) => [(int) $count => (int) $bonus])
            ->filter(fn ($bonus, $count) => $count > 0 && $bonus !== 0)
            ->sortKeys()
            ->all();

        return [
            'attend' => (int) $rules['attend'],
            'break_bonus' => (int) $rules['break_bonus'],
            'committee' => (int) $rules['committee'],
            'no_show' => abs((int) $rules['no_show']),
            'milestones' => $milestones,
        ];
    }

    public function saveRules(array $rules): void
    {
        Setting::put('merit_rules', json_encode($rules));
    }

    public function resetCache(): void
    {
        $this->pauses = null;
    }

    public function ready(): bool
    {
        return DatabaseStatus::hasTable('merit_entries');
    }

    /** Recalculate one member's automatic merit. */
    public function syncUser(User $user): void
    {
        if (! $this->ready()) {
            return;
        }

        $existing = MeritEntry::query()->where('user_id', $user->id)->where('source', '!=', 'manual')->get()->keyBy('auto_key');

        $wanted = $user->earnsMerit() ? $this->wantedFor($user) : collect();

        foreach ($existing as $key => $entry) {
            if (! $wanted->has($key)) {
                $entry->delete();
            }
        }

        foreach ($wanted as $key => $w) {
            $entry = $existing->get($key);
            if ($entry) {
                if ($entry->points !== $w['points'] || $entry->reason !== $w['reason']) {
                    $entry->forceFill(['points' => $w['points'], 'reason' => $w['reason']])->save();
                }

                continue;
            }
            $new = new MeritEntry([
                'user_id' => $user->id,
                'points' => $w['points'],
                'source' => $w['source'],
                'auto_key' => $key,
                'program_id' => $w['program_id'],
                'reason' => $w['reason'],
            ]);
            // Date the entry by the event, so the history reads in the right order.
            $new->created_at = $w['at'];
            $new->updated_at = now();
            $new->save();
        }
    }

    /** Recalculate everyone (after the rules or semester breaks change). */
    public function syncAll(): int
    {
        if (! $this->ready()) {
            return 0;
        }
        $this->pauses = null;
        $n = 0;
        User::query()->approved()->whereIn('role', ['member', 'admin', 'superadmin'])->orderBy('id')
            ->chunkById(200, function ($users) use (&$n) {
                foreach ($users as $user) {
                    $this->syncUser($user);
                    $n++;
                }
            });

        return $n;
    }

    /** @return array{total:int, merit:int, demerit:int} */
    public function summary(User $user): array
    {
        if (! $this->ready()) {
            return ['total' => 0, 'merit' => 0, 'demerit' => 0];
        }
        $row = MeritEntry::query()->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) as merit, COALESCE(SUM(CASE WHEN points < 0 THEN points ELSE 0 END), 0) as demerit')
            ->toBase()->first();

        $merit = (int) ($row->merit ?? 0);
        $demerit = abs((int) ($row->demerit ?? 0));

        return ['total' => $merit - $demerit, 'merit' => $merit, 'demerit' => $demerit];
    }

    /**
     * Total points per member, highest first.
     *
     * @param  list<int>|null  $userIds
     * @return Collection<int, int> user id => points
     */
    public function totals(?array $userIds = null): Collection
    {
        if (! $this->ready()) {
            return collect();
        }

        return MeritEntry::query()
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(points) as total')
            ->toBase()->get()
            ->mapWithKeys(fn ($r) => [(int) $r->user_id => (int) $r->total])
            ->sortDesc();
    }

    /** @return Collection<string, array{points:int, source:string, program_id:?int, reason:string, at:Carbon}> */
    private function wantedFor(User $user): Collection
    {
        $rules = $this->rules();
        $wanted = collect();

        $regs = ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['attended', 'absent'])
            ->whereHas('program', fn ($q) => $q->where('status', '!=', 'cancelled')->where('counts_for_activity', true))
            ->with('program')
            ->get()
            ->filter(fn ($r) => $r->program)
            ->sortBy(fn ($r) => $r->program->starts_at);

        $attended = 0;
        foreach ($regs as $r) {
            $p = $r->program;
            if ($r->status === 'attended') {
                $attended++;
                if ($rules['attend'] !== 0) {
                    $wanted->put("attend:{$p->id}", ['points' => $rules['attend'], 'source' => 'attendance', 'program_id' => $p->id, 'reason' => "Attended {$p->title}", 'at' => $p->starts_at]);
                }
                if ($rules['break_bonus'] !== 0 && $this->inBreak($p->starts_at)) {
                    $wanted->put("break:{$p->id}", ['points' => $rules['break_bonus'], 'source' => 'break_bonus', 'program_id' => $p->id, 'reason' => "Semester break bonus: {$p->title}", 'at' => $p->starts_at]);
                }
                foreach ($rules['milestones'] as $count => $bonus) {
                    if ($attended === $count) {
                        $wanted->put("milestone:{$count}", ['points' => $bonus, 'source' => 'milestone', 'program_id' => null, 'reason' => "Milestone: {$count} events attended", 'at' => $p->starts_at]);
                    }
                }
            } elseif ($rules['no_show'] > 0) {
                $wanted->put("noshow:{$p->id}", ['points' => -$rules['no_show'], 'source' => 'no_show', 'program_id' => $p->id, 'reason' => "Registered but didn't attend {$p->title}", 'at' => $p->starts_at]);
            }
        }

        if ($rules['committee'] !== 0 && DatabaseStatus::hasTable('program_committee')) {
            ProgramCommittee::query()
                ->where('user_id', $user->id)
                ->whereHas('program', fn ($q) => $q->where('status', '!=', 'cancelled')->where('starts_at', '<=', now()))
                ->with('program')
                ->get()
                ->unique('program_id')
                ->each(function (ProgramCommittee $c) use ($wanted, $rules) {
                    $wanted->put("ajk:{$c->program_id}", ['points' => $rules['committee'], 'source' => 'committee', 'program_id' => $c->program_id, 'reason' => "AJK ({$c->position}): {$c->program->title}", 'at' => $c->program->starts_at]);
                });
        }

        return $wanted;
    }

    private function inBreak(Carbon $when): bool
    {
        if ($this->pauses === null) {
            $this->pauses = ActivityPause::query()->get()
                ->map(fn ($p) => [Carbon::parse($p->starts_on)->startOfDay(), Carbon::parse($p->ends_on)->endOfDay()])
                ->all();
        }
        foreach ($this->pauses as [$start, $end]) {
            if ($when->between($start, $end)) {
                return true;
            }
        }

        return false;
    }
}
