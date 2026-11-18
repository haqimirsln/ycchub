<?php

namespace App\Services;

use App\Models\ProgramRegistration;
use App\Models\Setting;
use App\Notifications\EventReminder;
use App\Support\DatabaseStatus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails members a reminder before an event they registered for, and sends a
 * phone notification to members who turned those on.
 * Run by the scheduler every five minutes; each registration is reminded once.
 */
class EventReminderService
{
    public const BATCH = 40;

    public const HOUR_CHOICES = [3, 6, 12, 24, 48];

    public function ready(): bool
    {
        return DatabaseStatus::hasColumn('program_registrations', 'reminded_at');
    }

    public function enabled(): bool
    {
        return Setting::get('event_reminders', '1') === '1';
    }

    public function hours(): int
    {
        $h = (int) Setting::get('event_reminder_hours', 24);

        return in_array($h, self::HOUR_CHOICES, true) ? $h : 24;
    }

    /** Sends the reminders that are due. Returns how many went out. */
    public function sendDue(): int
    {
        if (! $this->ready() || ! $this->enabled()) {
            return 0;
        }

        $due = ProgramRegistration::query()
            ->where('status', 'registered')
            ->whereNull('reminded_at')
            // Someone who registers at the last minute doesn't need a reminder straight after.
            ->where('registered_at', '<=', now()->subMinutes(30))
            ->whereHas('program', fn ($q) => $q->where('status', 'published')
                ->where('starts_at', '>', now()->addHour())
                ->where('starts_at', '<=', now()->addHours($this->hours())))
            ->whereHas('user', fn ($q) => $q->approved())
            ->with(['program', 'user'])
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();

        $sent = 0;
        foreach ($due as $reg) {
            // Mark first, so a crash halfway never sends the same reminder twice.
            $reg->forceFill(['reminded_at' => now()])->save();
            // The phone notification goes out even if the email fails.
            try {
                app(PushService::class)->queueReminder($reg);
            } catch (Throwable $e) {
                Log::warning('Event reminder push failed', ['registration' => $reg->id, 'error' => $e->getMessage()]);
            }
            try {
                $reg->user->notify(new EventReminder($reg->program));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Event reminder failed', ['registration' => $reg->id, 'error' => $e->getMessage()]);
                Setting::put('event_reminder_error', mb_substr("{$reg->user->email}: {$e->getMessage()}", 0, 255));
            }
        }
        if ($sent) {
            Setting::put('event_reminder_last', now()->toDateTimeString());
            Setting::put('event_reminder_total', (int) Setting::get('event_reminder_total', 0) + $sent);
        }

        return $sent;
    }
}
