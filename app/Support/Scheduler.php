<?php

namespace App\Support;

use App\Services\AnnouncementService;
use App\Services\EventReminderService;
use App\Services\PushService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the background jobs (phone notifications, event reminders, batched
 * club emails) moving even when the server's cron job isn't set up.
 *
 * The cron job is still the proper way: it ticks every minute. Without it,
 * visits to the site do the same work right after each page has been sent to
 * the browser, at most once a minute, so nobody waits on it.
 */
class Scheduler
{
    private const LAST_RUN = 'ycc.cron-last';

    private const LAST_ERROR = 'ycc.cron-error';

    /** Called by the cron job every minute. */
    public static function markRan(): void
    {
        Cache::forever(self::LAST_RUN, now()->timestamp);
    }

    /**
     * Runs one scheduled job, remembering when it last worked and the last error,
     * so Settings can show a job that keeps failing instead of just "Running".
     */
    public static function job(string $name, callable $run): void
    {
        try {
            $run();
            Cache::forever('ycc.job-ok.'.$name, now()->timestamp);
        } catch (Throwable $e) {
            Cache::forever(self::LAST_ERROR, ['job' => $name, 'message' => mb_substr($e->getMessage(), 0, 300), 'at' => now()->timestamp]);
            Log::error("Scheduled job {$name} failed", ['error' => $e->getMessage()]);
        }
    }

    /** @return array{job: string, message: string, at: Carbon}|null the last job failure in the past day */
    public static function lastError(): ?array
    {
        $e = Cache::get(self::LAST_ERROR);
        if (! is_array($e) || ($e['at'] ?? 0) < now()->subDay()->timestamp) {
            return null;
        }

        return ['job' => $e['job'], 'message' => $e['message'], 'at' => Carbon::createFromTimestamp((int) $e['at'])];
    }

    /** When this job last finished without an error. */
    public static function jobLastOk(string $name): ?Carbon
    {
        $ts = Cache::get('ycc.job-ok.'.$name);

        return $ts ? Carbon::createFromTimestamp((int) $ts) : null;
    }

    public static function lastRun(): ?Carbon
    {
        $ts = Cache::get(self::LAST_RUN);

        return $ts ? Carbon::createFromTimestamp((int) $ts) : null;
    }

    /**
     * True when the cron job ran in the last five minutes AND the jobs themselves
     * work. A heartbeat alone isn't enough: on hosts without proc_open the
     * heartbeat ticks while every real job fails.
     */
    public static function cronRunning(): bool
    {
        $last = self::lastRun();
        $push = self::jobLastOk('ycc:send-push');

        return $last !== null && $last->gt(now()->subMinutes(5))
            && $push !== null && $push->gt(now()->subMinutes(5));
    }

    /** The line to paste into cPanel › Cron Jobs (every minute). */
    public static function cronCommand(): string
    {
        return '/usr/local/bin/ea-php83 '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }

    /** Runs after a page is sent, when the cron job isn't doing the work. At most once a minute. */
    public static function fallback(): void
    {
        try {
            if (DatabaseStatus::pending() || self::cronRunning() || ! Cache::add('ycc.web-cron', 1, 55)) {
                return;
            }

            @set_time_limit(60);
            foreach ([
                fn () => app(PushService::class)->sendDue(40),
                fn () => app(EventReminderService::class)->sendDue(),
                fn () => app(AnnouncementService::class)->sendDue(),
            ] as $job) {
                try {
                    $job();
                } catch (Throwable $e) {
                    Log::warning('Background job (no cron) failed', ['error' => $e->getMessage()]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Background jobs could not start', ['error' => $e->getMessage()]);
        }
    }
}
