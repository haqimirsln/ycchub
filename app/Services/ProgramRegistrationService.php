<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProgramRegistrationService
{
    public function __construct(private ActivityStatusService $activity) {}

    /** Member joins an event. Capacity is checked under a row lock so the last seat cannot be taken twice. */
    public function register(Program $program, User $user): ProgramRegistration
    {
        if (! $user->isApproved()) {
            throw new RegistrationException('Your account is not approved yet.');
        }

        return DB::transaction(function () use ($program, $user) {
            /** @var Program $locked */
            $locked = Program::query()->whereKey($program->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'published') {
                throw new RegistrationException('This event is not open for registration.');
            }
            if (! $locked->isRegistrationOpen()) {
                throw new RegistrationException('Registration for this event has closed.');
            }

            $existing = ProgramRegistration::query()
                ->where('program_id', $locked->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status !== 'cancelled') {
                throw new RegistrationException('You are already registered for this event.');
            }

            if ($locked->max_capacity !== null) {
                $taken = ProgramRegistration::query()
                    ->where('program_id', $locked->id)
                    ->whereIn('status', ['registered', 'attended', 'absent'])
                    ->count();
                if ($taken >= $locked->max_capacity) {
                    throw new RegistrationException('Sorry, this event is full.');
                }
            }

            if ($existing) {
                $existing->update([
                    'status' => 'registered',
                    'registered_at' => now(),
                    'cancelled_at' => null,
                ]);

                return $existing;
            }

            return ProgramRegistration::create([
                'program_id' => $locked->id,
                'user_id' => $user->id,
                'status' => 'registered',
                'registered_at' => now(),
            ]);
        });
    }

    /** Member cancels before the event starts. */
    public function cancel(Program $program, User $user): ProgramRegistration
    {
        $registration = ProgramRegistration::query()
            ->where('program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $registration || $registration->status !== 'registered') {
            throw new RegistrationException('You do not have an active registration for this event.');
        }
        if ($program->hasStarted()) {
            throw new RegistrationException('This event has already started, so the registration can no longer be cancelled.');
        }

        $registration->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $registration;
    }

    /**
     * Exco marks one member. $status is attended, absent, or registered (reset).
     * Walk-ins (no registration yet) are created on the spot when marked attended.
     */
    public function markAttendance(Program $program, User $member, string $status, User $by, string $method = 'manual'): ProgramRegistration
    {
        if (! in_array($status, ['attended', 'absent', 'registered'], true)) {
            throw new RegistrationException('Invalid attendance status.');
        }

        $registration = ProgramRegistration::query()->firstOrNew([
            'program_id' => $program->id,
            'user_id' => $member->id,
        ]);

        if (! $registration->exists) {
            if ($status !== 'attended') {
                throw new RegistrationException('This member is not registered for the event.');
            }
            $registration->registered_at = now();
        }

        $registration->status = $status;
        if ($status === 'attended') {
            $registration->checked_in_at = $registration->checked_in_at ?? now();
            $registration->checked_in_by = $by->id;
            $registration->checkin_method = $method;
            $registration->cancelled_at = null;
        } else {
            $registration->checked_in_at = null;
            $registration->checked_in_by = $status === 'absent' ? $by->id : null;
            $registration->checkin_method = null;
        }
        $registration->save();

        $this->activity->refreshUser($member);

        return $registration;
    }

    /** Mark everyone still "registered" as absent (used once the event is over). */
    public function markRemainingAbsent(Program $program, User $by): int
    {
        $ids = $program->registrations()->where('status', 'registered')->pluck('user_id')->all();
        if (! $ids) {
            return 0;
        }

        $program->registrations()->where('status', 'registered')->update([
            'status' => 'absent',
            'checked_in_by' => $by->id,
            'updated_at' => now(),
        ]);
        $this->activity->refreshAll($ids);

        return count($ids);
    }

    /** Member scans the event QR code. */
    public function selfCheckIn(Program $program, User $user): ProgramRegistration
    {
        if (! $user->isApproved()) {
            throw new RegistrationException('Your account is not approved yet.');
        }
        if (! $program->checkin_open || $program->status !== 'published') {
            throw new RegistrationException('Check-in for this event is not open right now. Ask the MT to open it.');
        }
        $now = now();
        $windowStart = $program->starts_at->copy()->startOfDay();
        $windowEnd = $program->endsAtOrDefault()->copy()->endOfDay();
        if (! $now->between($windowStart, $windowEnd)) {
            throw new RegistrationException('Check-in is only available on the day of the event.');
        }

        $existing = $program->registrations()->where('user_id', $user->id)->first();
        if ($existing && $existing->status === 'attended') {
            return $existing;
        }

        return $this->markAttendance($program, $user, 'attended', $user, 'qr');
    }
}
