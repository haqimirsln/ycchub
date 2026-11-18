<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Writes one line to the superadmin's activity log. Never breaks the action being logged. */
class Activity
{
    public static function log(string $action, string $description, ?Model $subject = null, ?int $actorId = null): void
    {
        try {
            if (! DatabaseStatus::hasTable('activity_logs')) {
                return;
            }
            ActivityLog::query()->create([
                'user_id' => $actorId ?? auth()->id(),
                'action' => $action,
                'description' => mb_substr($description, 0, 255),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Logging must never stop the real work.
        }
    }
}
