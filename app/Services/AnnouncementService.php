<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\ClubAnnouncement;
use App\Support\DatabaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Club emails from the MT to a chosen group of members.
 *
 * Shared hosting stops a page after about 30 seconds and limits emails per
 * hour, so recipients are saved on the announcement and sent a few at a time:
 * the first batch right away, the rest by the scheduler every minute (or by
 * pressing "Send next batch" when the cron job isn't running).
 */
class AnnouncementService
{
    public const BATCH = 15;

    public function ready(): bool
    {
        return DatabaseStatus::hasTable('announcements') && DatabaseStatus::hasColumn('users', 'email_opt_out');
    }

    /**
     * Cleans the audience picked on the form.
     *
     * @return array{roles: list<string>, teams: list<string>, statuses: list<string>, types: list<string>}
     */
    public function audience(array $input): array
    {
        $pick = fn (string $key, array $allowed) => array_values(array_intersect((array) ($input[$key] ?? []), $allowed));

        return [
            'roles' => $pick('roles', ['member', 'mt', 'advisor']),
            'teams' => $pick('teams', [...array_keys(config('ycc.teams')), 'none']),
            'statuses' => $pick('statuses', array_keys(User::ACTIVITY_LABELS)),
            'types' => $pick('types', array_keys(User::MEMBER_TYPES)),
        ];
    }

    /** Approved members matching the audience who haven't turned club emails off. An empty list means "any". */
    public function recipients(array $audience): Builder
    {
        $a = $this->audience($audience);

        return User::query()->approved()
            ->when($a['roles'] !== [], fn ($q) => $q->whereIn('role', collect($a['roles'])
                ->flatMap(fn ($r) => ['member' => ['member'], 'mt' => ['admin', 'superadmin'], 'advisor' => ['advisor']][$r])->all()))
            ->when($a['teams'] !== [], function ($q) use ($a) {
                $named = array_values(array_diff($a['teams'], ['none']));
                $q->where(function ($w) use ($named, $a) {
                    if ($named) {
                        $w->whereIn('team', $named);
                    }
                    if (in_array('none', $a['teams'], true)) {
                        $w->orWhereNull('team')->orWhere('team', '');
                    }
                });
            })
            ->when($a['statuses'] !== [], fn ($q) => $q->whereIn('activity_status', $a['statuses']))
            ->when($a['types'] !== [], fn ($q) => $q->whereIn('member_type', $a['types']))
            ->when(DatabaseStatus::hasColumn('users', 'email_opt_out'), fn ($q) => $q->where('email_opt_out', false));
    }

    /** Plain words for who an email went to, e.g. "Members · Multimedia Team · Active". */
    public function describe(array $audience): string
    {
        $a = $this->audience($audience);
        $parts = [];
        if ($a['roles'] !== [] && count($a['roles']) < 3) {
            $parts[] = collect($a['roles'])->map(fn ($r) => ['member' => 'Members', 'mt' => 'MT', 'advisor' => 'Advisors'][$r])->implode(' + ');
        }
        if ($a['teams']) {
            $parts[] = collect($a['teams'])->map(fn ($t) => $t === 'none' ? 'No team yet' : config("ycc.teams.$t"))->implode(', ');
        }
        if ($a['statuses']) {
            $parts[] = collect($a['statuses'])->map(fn ($s) => User::ACTIVITY_LABELS[$s])->implode(', ');
        }
        if (count($a['types']) === 1) {
            $parts[] = $a['types'][0] === 'alumni' ? 'Alumni' : 'UMT students';
        }

        return $parts ? implode(' · ', $parts) : 'Everyone';
    }

    /** Saves the email, works out who gets it and sends the first batch. */
    public function start(Announcement $announcement): Announcement
    {
        $ids = $this->recipients($announcement->audience ?? [])->orderBy('id')->pluck('id')->all();

        $announcement->audience_label = $this->describe($announcement->audience ?? []);
        $announcement->recipient_count = count($ids);
        $announcement->pending_ids = $ids;
        $announcement->finished_at = $ids ? null : now();
        $announcement->save();

        $this->sendBatch($announcement);

        return $announcement->refresh();
    }

    /** Sends the next few emails of one announcement. Returns how many went out. */
    public function sendBatch(Announcement $announcement, int $size = self::BATCH): int
    {
        $queue = $announcement->pending_ids ?? [];
        if (! $queue || $announcement->cancelled_at) {
            return 0;
        }

        $batch = array_splice($queue, 0, $size);
        // Save first, so a crash halfway never sends someone the same email twice.
        $announcement->pending_ids = $queue;
        $announcement->save();

        $sent = 0;
        $failed = 0;
        $users = User::query()->approved()->whereIn('id', $batch)
            ->when(DatabaseStatus::hasColumn('users', 'email_opt_out'), fn ($q) => $q->where('email_opt_out', false))
            ->get();
        foreach ($users as $user) {
            try {
                $user->notify(new ClubAnnouncement($announcement));
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $announcement->last_error = mb_substr("{$user->email}: {$e->getMessage()}", 0, 255);
                Log::warning('Club email failed', ['announcement' => $announcement->id, 'user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        $announcement->sent_count += $sent;
        $announcement->failed_count += $failed;
        if (! $queue) {
            $announcement->finished_at = now();
        }
        $announcement->save();

        return $sent;
    }

    /** Called every minute by the scheduler: one batch of the oldest email still going out. */
    public function sendDue(): int
    {
        if (! $this->ready()) {
            return 0;
        }

        $next = Announcement::query()->whereNull('finished_at')->whereNull('cancelled_at')->orderBy('id')->first();

        return $next ? $this->sendBatch($next) : 0;
    }

    public function cancel(Announcement $announcement): int
    {
        $left = $announcement->remaining();
        $announcement->pending_ids = [];
        $announcement->cancelled_at = now();
        $announcement->finished_at = now();
        $announcement->save();

        return $left;
    }

    /** Ready-made starting points on the compose form. {name} becomes each member's first name. */
    public static function templates(): array
    {
        $teams = collect(config('ycc.teams'))
            ->map(fn ($name, $key) => '<li><strong>'.e($name).'</strong>: '.e(config("ycc.team_blurbs.$key")).'</li>')
            ->implode('');

        return [
            'blank' => [
                'label' => 'Blank',
                'subject' => '',
                'body' => '<p>Hi {name},</p><p></p>',
                'button_label' => '',
                'button_url' => '',
                'audience' => [],
            ],
            'event' => [
                'label' => 'Promote an event',
                'subject' => 'Join us at our next event',
                'body' => '<p>Hi {name},</p><p>We have something coming up and we\'d love to see you there. All the details are below. <strong>Seats are limited</strong>, so register early in YCC Hub.</p><p>Bring a friend who cares about the ocean too. They can sign up on the YCC Hub website.</p>',
                'button_label' => '',
                'button_url' => '',
                'audience' => [],
                'needs_event' => true,
            ],
            'comeback' => [
                'label' => 'We miss you',
                'subject' => 'We miss you at YCC',
                'body' => '<p>Hi {name},</p><p>It\'s been a while since we saw you at a YCC event. No pressure, we know classes get busy.</p><p>When you have a free weekend, have a look at what\'s coming up. One event is all it takes to be <strong>Active</strong> again, and every event earns you merit.</p>',
                'button_label' => 'See upcoming events',
                'button_url' => route('programs.index'),
                'audience' => ['statuses' => ['at_risk', 'inactive']],
            ],
            'pick_team' => [
                'label' => 'Pick your team',
                'subject' => 'Pick your YCC team (takes 30 seconds)',
                'body' => '<h2>Hi {name}, which team are you?</h2><p>YCC now runs as <strong>four teams</strong>, and YCC Hub has a new spot in your profile to choose yours. It helps the MT plan events, share tasks and know who to call for what.</p><ul>'.$teams.'</ul>'
                    .'<h3>How to do it</h3><ol><li>Tap <strong>Choose my team</strong> below, or open YCC Hub and go to <strong>My profile</strong>.</li><li>Log in with your matric number ({matric}) if it asks.</li><li>Under <strong>Team</strong>, tap the one you want.</li><li>Scroll down and press <strong>Save profile</strong>. That\'s it.</li></ol>'
                    .'<p>Not sure which one fits? Pick the closest. You can change it any time.</p>',
                'button_label' => 'Choose my team',
                'button_url' => route('profile.edit').'#team',
                'audience' => ['teams' => ['none']],
            ],
        ];
    }
}
