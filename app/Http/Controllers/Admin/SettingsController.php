<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicController;
use App\Models\ActivityPause;
use App\Models\ExportLog;
use App\Models\InstagramPost;
use App\Models\Setting;
use App\Services\ActivityStatusService;
use App\Services\InstagramFeedService;
use App\Services\MeritService;
use App\Services\EventReminderService;
use App\Support\Activity;
use App\Support\DatabaseStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(InstagramFeedService $instagram, MeritService $merit, EventReminderService $reminders): View
    {
        $token = $instagram->token();

        return view('admin.settings', [
            'social' => PublicController::social(),
            'pauses' => ActivityPause::query()->orderByDesc('starts_on')->get(),
            'instagram' => [
                'configured' => filled($token),
                'token_hint' => $token ? substr($token, 0, 6).'…'.substr($token, -4) : null,
                'expires_at' => Setting::get('instagram_token_expires_at'),
                'last_sync' => Setting::get('instagram_last_sync_at'),
                'last_error' => Setting::get('instagram_last_error'),
                'api_count' => InstagramPost::query()->where('source', 'api')->count(),
                'pinned' => InstagramPost::query()->where('source', 'manual')->orderByDesc('posted_at')->pluck('permalink')->implode("\n"),
            ],
            'rules' => config('ycc.activity'),
            'pendingMigrations' => DatabaseStatus::pending(),
            'meritRules' => $merit->rules(),
            'meritReady' => $merit->ready(),
            'reminders' => [
                'ready' => $reminders->ready(),
                'enabled' => $reminders->enabled(),
                'hours' => $reminders->hours(),
                'total' => (int) Setting::get('event_reminder_total', 0),
                'last' => Setting::get('event_reminder_last'),
                'error' => Setting::get('event_reminder_error'),
            ],
        ]);
    }

    public function social(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instagram_handle' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'],
            'instagram_url' => ['required', 'url', 'max:255'],
            'linkedin_url' => ['required', 'url', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }
        Activity::log('system.settings', 'Updated the social links');

        return back()->with('success', 'Social links saved.');
    }

    public function instagram(Request $request, InstagramFeedService $instagram): RedirectResponse
    {
        $data = $request->validate([
            'access_token' => ['nullable', 'string', 'max:1000'],
            'clear_token' => ['nullable', 'boolean'],
            'pinned_urls' => ['nullable', 'string', 'max:5000'],
        ]);

        $messages = [];

        if ($request->boolean('clear_token')) {
            Setting::put('instagram_access_token', null);
            Setting::put('instagram_token_expires_at', null);
            InstagramPost::query()->where('source', 'api')->delete();
            $messages[] = 'Instagram token removed.';
        } elseif (filled($data['access_token'] ?? null)) {
            Setting::put('instagram_access_token', trim($data['access_token']));
            Setting::put('instagram_token_expires_at', now()->addDays(60)->toDateTimeString());
            $synced = $instagram->sync();
            $messages[] = $synced ? "Token saved and {$synced} posts pulled from Instagram." : 'Token saved, but the first sync failed. Check the error below.';
        }

        $pinned = $instagram->savePinnedUrls((string) ($data['pinned_urls'] ?? ''));
        $messages[] = "{$pinned} pinned post link(s) saved.";

        return back()->with('success', implode(' ', $messages));
    }

    public function syncInstagram(InstagramFeedService $instagram): RedirectResponse
    {
        if (! $instagram->isConfigured()) {
            return back()->with('error', 'Add an Instagram access token first.');
        }

        $count = $instagram->sync();

        return $count
            ? back()->with('success', "Synced {$count} posts from Instagram.")
            : back()->with('error', 'Sync failed. See the error under Instagram feed.');
    }

    public function storePause(Request $request, ActivityStatusService $activity): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        ActivityPause::create($data);
        $counts = $activity->refreshAll();
        app(MeritService::class)->syncAll();
        Activity::log('system.settings', "Added semester break {$data['label']} ({$data['starts_on']} to {$data['ends_on']})");

        return back()->with('success', "Pause added and member statuses recalculated ({$counts['active']} active, {$counts['at_risk']} at-risk, {$counts['inactive']} inactive).");
    }

    public function destroyPause(ActivityPause $pause, ActivityStatusService $activity): RedirectResponse
    {
        $pause->delete();
        $activity->refreshAll();
        app(MeritService::class)->syncAll();
        Activity::log('system.settings', "Removed semester break {$pause->label}");

        return back()->with('success', 'Pause removed and statuses recalculated.');
    }

    public function recalculate(ActivityStatusService $activity): RedirectResponse
    {
        $c = $activity->refreshAll();

        return back()->with('success', "Recalculated: {$c['active']} active, {$c['at_risk']} at-risk, {$c['inactive']} inactive, {$c['new']} new.");
    }

    /**
     * Applies database changes that come with an uploaded update (the same as
     * running "php artisan migrate" over SSH). Safe to press more than once.
     */
    public function migrate(): RedirectResponse
    {
        $pending = count(DatabaseStatus::pending());

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            Log::error('Database update failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Database update failed: '.$e->getMessage());
        } finally {
            DatabaseStatus::forget();
        }

        Activity::log('system.database', $pending ? "Updated the database ({$pending} ".($pending === 1 ? 'change' : 'changes').')' : 'Pressed Update database (already up to date)');

        return back()->with('success', $pending
            ? "Database updated ({$pending} ".($pending === 1 ? 'change' : 'changes').' applied).'
            : 'Database was already up to date.');
    }

    /** Saves the merit points rules and recalculates everyone's automatic merit. */
    public function merit(Request $request, MeritService $merit): RedirectResponse
    {
        $data = $request->validate([
            'attend' => ['required', 'integer', 'min:0', 'max:100'],
            'break_bonus' => ['required', 'integer', 'min:0', 'max:100'],
            'committee' => ['required', 'integer', 'min:0', 'max:100'],
            'no_show' => ['required', 'integer', 'min:0', 'max:100'],
            'milestones' => ['nullable', 'string', 'max:200', 'regex:/^\s*(\d+\s*:\s*\d+\s*(,\s*\d+\s*:\s*\d+\s*)*)?$/'],
        ], [
            'milestones.regex' => 'Write milestones as events:points pairs, e.g. 5:10, 10:20, 20:30',
        ]);

        $milestones = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($data['milestones'] ?? '')))) as $pair) {
            [$count, $bonus] = array_map('intval', explode(':', $pair));
            if ($count > 0 && $bonus > 0) {
                $milestones[$count] = min($bonus, 500);
            }
        }
        ksort($milestones);

        $merit->saveRules([
            'attend' => (int) $data['attend'],
            'break_bonus' => (int) $data['break_bonus'],
            'committee' => (int) $data['committee'],
            'no_show' => (int) $data['no_show'],
            'milestones' => $milestones,
        ]);
        $n = $merit->syncAll();
        Activity::log('merit.rules', 'Changed the merit rules');

        return back()->with('success', "Merit rules saved and recalculated for {$n} members.");
    }

    public function reminders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event_reminders' => ['nullable', 'boolean'],
            'event_reminder_hours' => ['required', Rule::in(EventReminderService::HOUR_CHOICES)],
        ]);
        Setting::put('event_reminders', $request->boolean('event_reminders') ? '1' : '0');
        Setting::put('event_reminder_hours', (string) $data['event_reminder_hours']);
        Activity::log('system.settings', $request->boolean('event_reminders')
            ? "Event reminders on, {$data['event_reminder_hours']} hours before"
            : 'Event reminders off');

        return back()->with('success', 'Reminder settings saved.');
    }

    /** Sends the real approval email to the admin, to check mail settings without the terminal. */
    public function testMail(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $user->notify(new \App\Notifications\AccountReviewed(true));
        } catch (Throwable $e) {
            Log::error('Test email failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Email failed: '.$e->getMessage());
        }

        return back()->with('success', "Test email sent to {$user->email}. Check the inbox and spam folder.");
    }

    public function exports(): View
    {
        return view('admin.exports', [
            'logs' => ExportLog::query()->with('user:id,full_name,matric_no')->latest('created_at')->paginate(40),
        ]);
    }
}
