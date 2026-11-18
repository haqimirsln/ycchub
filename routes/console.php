<?php

use App\Models\User;
use App\Services\ActivityStatusService;
use App\Services\InstagramFeedService;
use App\Services\AnnouncementService;
use App\Services\EventReminderService;
use App\Services\PushService;
use App\Support\Scheduler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ycc:refresh-activity', function (ActivityStatusService $activity) {
    $c = $activity->refreshAll();
    $this->info("Active {$c['active']} | At-risk {$c['at_risk']} | Inactive {$c['inactive']} | New {$c['new']}");
})->purpose('Recalculate every member\'s activity status');

Artisan::command('ycc:instagram-sync', function (InstagramFeedService $instagram) {
    if (! $instagram->isConfigured()) {
        $this->warn('No Instagram token set. Skipping.');

        return;
    }
    $this->info('Synced '.$instagram->sync().' posts.');
})->purpose('Pull the latest Instagram posts into the cache');

Artisan::command('ycc:instagram-refresh-token', function (InstagramFeedService $instagram) {
    $this->info($instagram->refreshToken() ? 'Token refreshed.' : 'Token not refreshed (missing or failed).');
})->purpose('Extend the Instagram long-lived token by another 60 days');

Artisan::command('ycc:make-admin {email} {--super : Make them superadmin instead of MT admin}', function (string $email) {
    $user = User::query()->where('email', strtolower($email))->first();
    if (! $user) {
        $this->error('No user with that email.');

        return 1;
    }
    $role = $this->option('super') ? 'superadmin' : 'admin';
    $user->forceFill(['role' => $role, 'account_status' => 'approved', 'reviewed_at' => $user->reviewed_at ?? now()])->save();
    $this->info("{$user->full_name} is now ".($role === 'superadmin' ? 'a superadmin.' : 'an MT admin.'));
})->purpose('Promote an existing account to MT admin (or superadmin with --super)');

Artisan::command('ycc:send-emails', function (AnnouncementService $emails) {
    $sent = $emails->sendDue();
    if ($sent) {
        $this->info("Sent {$sent} club emails.");
    }
})->purpose('Send the next batch of club emails from the Email members page, if any are waiting');

Artisan::command('ycc:event-reminders', function (EventReminderService $reminders) {
    $sent = $reminders->sendDue();
    if ($sent) {
        $this->info("Sent {$sent} event reminders.");
    }
})->purpose('Email members a reminder before events they registered for');

Artisan::command('ycc:send-push', function (PushService $push) {
    $sent = $push->sendDue();
    if ($sent) {
        $this->info("Sent phone notifications to {$sent} members.");
    }
})->purpose('Send queued phone notifications (event reminders and new events)');

// cPanel cron (every minute): /usr/local/bin/ea-php83 /home/USER/public_html/ycc-hub/artisan schedule:run
//
// Every job runs inside the cron process itself (Schedule::call), never as a
// separate process (Schedule::command), because shared hosting often disables
// proc_open and the scheduler then fails before any job starts.
$job = fn (string $command) => fn () => Scheduler::job($command, fn () => Artisan::call($command));

Schedule::call($job('ycc:send-push'))->everyMinute()->name('ycc:send-push')->withoutOverlapping(5);
Schedule::call($job('ycc:send-emails'))->everyMinute()->name('ycc:send-emails')->withoutOverlapping(10);
Schedule::call($job('ycc:event-reminders'))->everyFiveMinutes()->name('ycc:event-reminders')->withoutOverlapping(10);
Schedule::call($job('ycc:instagram-sync'))->everyThirtyMinutes()->name('ycc:instagram-sync')->withoutOverlapping(10);
Schedule::call($job('ycc:refresh-activity'))->dailyAt('00:15')->name('ycc:refresh-activity')->withoutOverlapping(30);
Schedule::call($job('ycc:instagram-refresh-token'))->weeklyOn(1, '03:30')->name('ycc:instagram-refresh-token');
// Lets Settings show whether the cron job is running.
Schedule::call(fn () => Scheduler::markRan())->everyMinute()->name('ycc:heartbeat');
