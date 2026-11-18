<?php

namespace App\Services;

use App\Models\MtTerm;
use App\Models\Setting;
use App\Models\User;
use App\Support\Activity;
use App\Support\DatabaseStatus;
use Illuminate\Support\Collection;

/**
 * MT positions (jawatan) and sessions.
 *
 * A position belongs to a session, so after handover last year's MT stays on
 * record as "Past MT" and in each person's history. Holding a position makes
 * an account MT (admin); one person holds one position per session.
 */
class MtService
{
    /** @var array<int, string>|null user id => position key, for the current session */
    private ?array $map = null;

    public function ready(): bool
    {
        return DatabaseStatus::hasTable('mt_terms');
    }

    public function session(): string
    {
        return (string) Setting::get('mt_session', '2026/2027');
    }

    public function advisor(): ?string
    {
        return Setting::get('mt_advisor');
    }

    public function isPublic(): bool
    {
        return Setting::get('mt_public', '1') === '1';
    }

    public function flush(): void
    {
        $this->map = null;
    }

    /** Current session's MT, in the order of config('ycc.positions'), with empty positions included. */
    public function current(): Collection
    {
        if (! $this->ready()) {
            return collect();
        }
        $terms = MtTerm::query()->with('user')->where('session', $this->session())->get()->keyBy('position');

        return collect(array_keys(config('ycc.positions')))
            ->map(fn ($key) => $terms->get($key) ?? new MtTerm(['session' => $this->session(), 'position' => $key]))
            ->values();
    }

    /** Past sessions, newest first: session => terms. */
    public function past(): Collection
    {
        if (! $this->ready()) {
            return collect();
        }
        $order = array_flip(array_keys(config('ycc.positions')));

        return MtTerm::query()->with('user')->where('session', '!=', $this->session())->get()
            ->groupBy('session')
            ->sortKeysDesc()
            ->map(fn ($terms) => $terms->sortBy(fn ($t) => $order[$t->position] ?? 99)->values());
    }

    public function positionOf(User $user): ?string
    {
        if (! $this->ready()) {
            return null;
        }
        $this->map ??= MtTerm::query()->where('session', $this->session())->whereNotNull('user_id')
            ->pluck('position', 'user_id')->all();

        return $this->map[$user->getKey()] ?? null;
    }

    public function labelOf(User $user): ?string
    {
        $key = $this->positionOf($user);

        return $key ? config("ycc.positions.$key.label") : null;
    }

    /** The current Ketua of a team, if that position is filled. */
    public function leaderOf(?string $team): ?MtTerm
    {
        if (! $team || ! $this->ready()) {
            return null;
        }
        $position = collect(config('ycc.positions'))->search(fn ($p) => ($p['team'] ?? null) === $team);

        return $position ? MtTerm::query()->with('user')->where('session', $this->session())->where('position', $position)->first() : null;
    }

    /** The team this user leads in the current session, if any. */
    public function teamLedBy(User $user): ?string
    {
        $key = $this->positionOf($user);

        return $key ? config("ycc.positions.$key.team") : null;
    }

    /** Every position someone has held, newest session first. */
    public function history(User $user): Collection
    {
        return $this->ready()
            ? MtTerm::query()->where('user_id', $user->getKey())->orderByDesc('session')->get()
            : collect();
    }

    /**
     * Gives a position to an account for the current session (or clears it with null).
     * The previous holder loses the position but keeps their role; the new holder becomes MT.
     */
    public function assign(User $user, ?string $position): void
    {
        $session = $this->session();
        MtTerm::query()->where('session', $session)->where('user_id', $user->id)->delete();

        if ($position) {
            MtTerm::query()->updateOrCreate(
                ['session' => $session, 'position' => $position],
                ['user_id' => $user->id, 'holder_name' => $user->full_name, 'holder_matric' => $user->matric_no, 'sort' => array_search($position, array_keys(config('ycc.positions')), true)],
            );
            if ($user->role === 'member') {
                $user->forceFill(['role' => 'admin'])->save();
            }
        }
        $this->flush();
    }

    /**
     * When someone on the MT list signs up and is approved, link them to their
     * position and make them MT. Returns the position label, if any.
     */
    public function linkOnApproval(User $user): ?string
    {
        $term = $this->pendingTermFor($user);
        if (! $term) {
            return null;
        }
        $term->forceFill(['user_id' => $user->id, 'holder_name' => $user->full_name])->save();
        if ($user->role === 'member') {
            $user->forceFill(['role' => 'admin'])->save();
        }
        $this->flush();

        return $term->label();
    }

    /** A current-session position waiting for this matric number to sign up. */
    public function pendingTermFor(User $user): ?MtTerm
    {
        return $this->ready()
            ? MtTerm::query()->where('session', $this->session())->whereNull('user_id')->where('holder_matric', $user->matric_no)->first()
            : null;
    }

    /**
     * Handover: the current MT becomes "Past MT" and a new, empty session starts.
     * Optionally turns last session's MT (except superadmins) back into members.
     */
    public function startSession(string $session, bool $demote): int
    {
        $old = $this->session();
        $demoted = 0;
        if ($demote) {
            $ids = MtTerm::query()->where('session', $old)->whereNotNull('user_id')->pluck('user_id');
            $demoted = User::query()->whereIn('id', $ids)->where('role', 'admin')->update(['role' => 'member']);
        }
        Setting::put('mt_session', $session);
        $this->flush();
        Activity::log('system.settings', "Started MT session {$session} (handover from {$old})".($demoted ? ", {$demoted} past MT back to members" : ''));

        return $demoted;
    }
}
