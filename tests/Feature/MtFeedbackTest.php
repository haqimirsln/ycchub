<?php

namespace Tests\Feature;

use App\Http\Middleware\HoldDuringDatabaseUpdate;
use App\Models\ActivityLog;
use App\Models\ActivityPause;
use App\Models\MeritEntry;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\ActivityStatusService;
use App\Services\MeritService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use App\Models\Announcement;
use App\Notifications\EventReminder;
use App\Notifications\ClubAnnouncement;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Roles, teams, merit, AJK and the activity log from the MT meeting. */
class MtFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function attend(User $user, Program $program): void
    {
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $user->id, 'status' => 'attended', 'registered_at' => $program->starts_at->copy()->subDay()]);
    }

    public function test_only_superadmin_deletes_members_and_events(): void
    {
        $mt = User::factory()->admin()->create();
        $super = User::factory()->superadmin()->create();
        $member = User::factory()->create();
        $program = Program::factory()->create();

        $this->actingAs($mt)->delete(route('admin.users.destroy', $member))->assertForbidden();
        $this->actingAs($mt)->delete(route('exco.programs.destroy', $program))->assertForbidden();
        $this->actingAs($mt)->get(route('exco.programs.edit', $program))->assertOk()->assertDontSee('btn-danger"><svg', false);

        $this->actingAs($super)->delete(route('admin.users.destroy', $super))->assertSessionHas('error');
        $this->actingAs($super)->delete(route('admin.users.destroy', $member))->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->actingAs($super)->delete(route('exco.programs.destroy', $program))->assertRedirect();
        $this->assertSoftDeleted($program);

        $this->assertEqualsCanonicalizing(['member.deleted', 'event.deleted'], ActivityLog::pluck('action')->all());
    }

    public function test_activity_log_is_for_superadmin_and_filters_by_type(): void
    {
        $super = User::factory()->superadmin()->create();
        $pending = User::factory()->create(['account_status' => 'pending', 'full_name' => 'Waiting Person']);
        $this->actingAs(User::factory()->admin()->create())->post(route('exco.approvals.approve', $pending));
        $this->actingAs($super)->post(route('exco.programs.store'), [
            'title' => 'Mangrove Day', 'description' => 'Planting.', 'category' => 'talk', 'venue' => 'Setiu',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'status' => 'published', 'counts_for_activity' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($super)->get(route('admin.activity'))->assertOk()
            ->assertSee('Approved Waiting Person')->assertSee('Created event: Mangrove Day');
        $this->actingAs($super)->get(route('admin.activity', ['group' => 'events']))->assertOk()
            ->assertSee('Mangrove Day')->assertDontSee('Approved Waiting Person');
        $this->actingAs($super)->get(route('admin.activity', ['q' => 'Waiting']))->assertOk()->assertDontSee('Mangrove Day');
    }

    public function test_members_earn_merit_with_break_bonus_and_milestones_but_mt_do_not(): void
    {
        $member = User::factory()->joinedDaysAgo(200)->create();
        $mt = User::factory()->admin()->joinedDaysAgo(200)->create();
        ActivityPause::create(['label' => 'Sem break', 'starts_on' => now()->subDays(40)->toDateString(), 'ends_on' => now()->subDays(30)->toDateString()]);

        foreach ([100, 80, 60, 35, 10] as $daysAgo) {
            $p = Program::factory()->create(['starts_at' => now()->subDays($daysAgo)]);
            $this->attend($member, $p);
            $this->attend($mt, $p);
        }
        app(ActivityStatusService::class)->refreshAll();
        $merit = app(MeritService::class);

        // 5 events x 5, one during the break +5, milestone at 5 events +10.
        $this->assertSame(['total' => 40, 'merit' => 40, 'demerit' => 0], $merit->summary($member));
        $this->assertSame(1, MeritEntry::where('user_id', $member->id)->where('source', 'break_bonus')->count());
        $this->assertSame(0, MeritEntry::where('user_id', $mt->id)->count(), 'MT do not collect merit');

        // Re-running never doubles up.
        app(ActivityStatusService::class)->refreshAll();
        $this->assertSame(40, $merit->summary($member)['total']);

        // The member sees it on their own page; MT see it in the members list.
        $this->actingAs($member)->get(route('merit.index'))->assertOk()->assertSee('Semester break bonus')->assertSee('Milestone: 5 events attended');
        $this->actingAs($mt)->get(route('merit.index'))->assertOk()->assertSee("MT and the superadmin don't collect merit", false);
        $this->assertSame(40, $this->actingAs($mt)->get('/exco/members?status=all')->assertOk()->viewData('meritTotals')[$member->id]);
    }

    public function test_mt_give_and_remove_manual_merit_and_demerit(): void
    {
        $mt = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($mt)->post(route('exco.merit.store', $member), ['kind' => 'demerit', 'points' => 3, 'reason' => 'Left the cleanup early'])->assertSessionHas('success');
        $this->actingAs($mt)->post(route('exco.merit.store', $member), ['kind' => 'merit', 'points' => 10, 'reason' => 'Won the poster contest'])->assertSessionHas('success');
        $this->actingAs($mt)->post(route('exco.merit.store', $member), ['kind' => 'merit', 'points' => 10])->assertSessionHasErrors('reason');
        $this->actingAs($mt)->post(route('exco.merit.store', $mt), ['kind' => 'merit', 'points' => 10, 'reason' => 'Self award'])->assertStatus(422);

        $this->assertSame(['total' => 7, 'merit' => 10, 'demerit' => 3], app(MeritService::class)->summary($member));
        $this->actingAs($mt)->get(route('exco.members.show', $member))->assertOk()->assertSee('Left the cleanup early');

        // Recalculating keeps entries given by hand.
        app(ActivityStatusService::class)->refreshAll();
        $this->assertSame(7, app(MeritService::class)->summary($member)['total']);

        $entry = MeritEntry::where('reason', 'Left the cleanup early')->first();
        $this->actingAs($mt)->delete(route('exco.merit.destroy', $entry))->assertSessionHas('success');
        $this->assertSame(10, app(MeritService::class)->summary($member)['total']);
        $this->assertSame(2, ActivityLog::where('action', 'merit.given')->count());
    }

    public function test_merit_rules_can_be_changed_in_settings(): void
    {
        $mt = User::factory()->admin()->create();
        $member = User::factory()->create();
        $p = Program::factory()->create(['starts_at' => now()->subDays(3)]);
        $this->attend($member, $p);
        ProgramRegistration::create(['program_id' => ($q = Program::factory()->create(['starts_at' => now()->subDays(2)]))->id, 'user_id' => $member->id, 'status' => 'absent', 'registered_at' => now()->subDays(4)]);
        app(ActivityStatusService::class)->refreshUser($member);
        $this->assertSame(5, app(MeritService::class)->summary($member)['total']);

        $this->actingAs($mt)->put(route('admin.settings.merit'), ['attend' => 8, 'break_bonus' => 5, 'committee' => 5, 'no_show' => 2, 'milestones' => 'nonsense'])
            ->assertSessionHasErrors('milestones');
        $this->actingAs($mt)->put(route('admin.settings.merit'), ['attend' => 8, 'break_bonus' => 5, 'committee' => 5, 'no_show' => 2, 'milestones' => '1:4, 10:20'])
            ->assertSessionHas('success');

        // +8 attend, +4 first-event milestone, -2 no-show.
        $this->assertSame(['total' => 10, 'merit' => 12, 'demerit' => 2], app(MeritService::class)->summary($member));
        $this->actingAs($mt)->get(route('admin.settings.edit'))->assertOk()->assertSee('1:4, 10:20');
    }

    public function test_event_committee_is_saved_shown_and_earns_ajk_merit(): void
    {
        $mt = User::factory()->admin()->create();
        $director = User::factory()->create(['full_name' => 'Pengarah Person']);
        $helper = User::factory()->create(['full_name' => 'Logistik Person']);
        $program = Program::factory()->create(['starts_at' => now()->subDay()]);

        $payload = [
            'title' => $program->title, 'description' => 'Done.', 'category' => $program->category, 'venue' => 'Pantai',
            'starts_at' => $program->starts_at->format('Y-m-d\TH:i'), 'status' => 'published', 'counts_for_activity' => '1',
            'committee_present' => '1', 'committee_count' => 12,
            'committee' => [
                ['position' => 'Pengarah Program', 'user_id' => $director->id],
                ['position' => 'AJK Logistik', 'user_id' => $helper->id],
                ['position' => 'AJK Makanan', 'user_id' => ''],
                ['position' => '', 'user_id' => ''],
            ],
        ];
        $this->actingAs($mt)->put(route('exco.programs.update', $program), $payload)->assertSessionHasNoErrors();

        $this->assertSame(12, $program->fresh()->committee_count);
        $this->assertSame(['Pengarah Program', 'AJK Logistik', 'AJK Makanan'], $program->committee()->pluck('position')->all());
        $this->assertSame(5, app(MeritService::class)->summary($director)['total']);

        $this->actingAs($director)->get(route('programs.show', $program))->assertOk()
            ->assertSee('Pengarah Program')->assertSee('Pengarah Person')->assertSee('To be decided')->assertSee('12');

        // Taking someone off the committee takes the AJK merit away again.
        $payload['committee'] = [['position' => 'Pengarah Program', 'user_id' => $director->id]];
        $this->actingAs($mt)->put(route('exco.programs.update', $program), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, app(MeritService::class)->summary($helper)['total']);
        $this->assertSame(5, app(MeritService::class)->summary($director)['total']);
    }

    public function test_existing_members_pick_a_team_in_their_profile_and_everyone_sees_club_stats(): void
    {
        $member = User::factory()->create(['team' => null]);
        User::factory()->count(3)->create();

        $this->actingAs($member)->get(route('portal'))->assertOk()->assertSee('Pick your YCC team')->assertSee('YCC right now');
        $club = $this->actingAs($member)->get(route('portal'))->viewData('club');
        $this->assertSame(4, $club['total']);

        $this->actingAs($member)->put(route('profile.update'), [
            'full_name' => $member->full_name, 'email' => $member->email, 'phone_number' => $member->phone_number,
            'member_type' => 'student', 'team' => 'entrepreneurship', 'faculty' => 'STEM Foundation', 'programme' => 'Asasi', 'year_of_study' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('entrepreneurship', $member->fresh()->team);
        $this->actingAs($member)->get(route('portal'))->assertDontSee('Pick your YCC team');
        $this->assertDatabaseHas('activity_logs', ['action' => 'member.team', 'user_id' => $member->id]);
    }

    public function test_site_holds_until_the_database_update_is_pressed(): void
    {
        HoldDuringDatabaseUpdate::$enabledInTests = true;
        $files = count(glob(database_path('migrations/*.php')));
        Cache::put('ycc.pending_migrations.'.$files, ['2099_01_01_000000_new_feature'], 300);

        try {
            $this->get('/')->assertStatus(503)->assertSee('Back in a minute');
            $this->get('/login')->assertOk();
            $this->actingAs(User::factory()->create())->get('/portal')->assertStatus(503);
            $mt = User::factory()->admin()->create();
            $this->actingAs($mt)->get('/portal')->assertRedirect(route('admin.settings.edit'));
            $this->actingAs($mt)->get(route('admin.settings.edit'))->assertOk()->assertSee('Update database');
        } finally {
            HoldDuringDatabaseUpdate::$enabledInTests = false;
            Cache::flush();
        }
    }

    public function test_mt_email_a_chosen_group_in_batches_and_members_can_opt_out(): void
    {
        Notification::fake();
        $mt = User::factory()->admin()->create(['team' => 'leadership']);
        $noTeam = User::factory()->count(20)->create(['team' => null]);
        $multimedia = User::factory()->create(['team' => 'multimedia', 'full_name' => 'Mia Media']);
        $optedOut = User::factory()->create(['team' => null, 'email_opt_out' => true]);
        User::factory()->create(['team' => null, 'account_status' => 'pending']);
        $event = Program::factory()->daysFromNow(5)->create(['title' => 'Mangrove Day']);

        $this->actingAs($mt)->get(route('exco.announcements.index'))->assertOk()->assertSee('Pick your team')->assertSee('1 member has turned club emails off');
        $this->assertSame(20, $this->actingAs($mt)->getJson(route('exco.announcements.count', ['teams' => ['none']]))->json('count'));
        $this->assertSame(21, $this->actingAs($mt)->getJson(route('exco.announcements.count', ['teams' => ['none', 'multimedia'], 'roles' => ['member']]))->json('count'));

        $mail = ['subject' => 'Hi {name}, join us', 'body' => "Hi {name},\n\nCome to **Mangrove Day**.", 'program_id' => $event->id, 'teams' => ['none']];

        $this->actingAs($mt)->post(route('exco.announcements.preview'), $mail)->assertOk()
            ->assertSee('Mangrove Day')->assertSee('View and register')->assertSee('Turn it off')->assertSee('Hi '.$mt->firstName());
        $this->actingAs($mt)->post(route('exco.announcements.store'), [...$mail, 'action' => 'test'])->assertSessionHas('success');
        Notification::assertSentTo($mt, ClubAnnouncement::class);
        $this->assertSame(0, Announcement::count(), 'a test send saves nothing');

        $this->actingAs($mt)->post(route('exco.announcements.store'), [...$mail, 'button_label' => 'Go'])->assertSessionHasErrors('button_url');
        $this->actingAs($mt)->post(route('exco.announcements.store'), [...$mail, 'action' => 'send'])
            ->assertSessionHas('success', 'Sent 15 of 20. The other 5 go out 15 a minute.');
        $a = Announcement::first();
        $this->assertSame('No team yet', $a->audience_label);

        $this->artisan('ycc:send-emails')->assertSuccessful();
        $this->assertSame(20, $a->fresh()->sent_count);
        $this->assertNotNull($a->fresh()->finished_at);
        Notification::assertNotSentTo([$multimedia, $optedOut], ClubAnnouncement::class);
        $first = $noTeam->first();
        Notification::assertSentTo($first, ClubAnnouncement::class, fn ($n) => $n->toMail($first)->subject === "Hi {$first->firstName()}, join us");
        $this->actingAs($mt)->get(route('exco.announcements.index'))->assertSee('20</span> of 20 sent', false);
        $this->assertDatabaseHas('activity_logs', ['action' => 'system.email']);

        // The link in the email turns club news off without logging in; the profile turns it back on.
        auth()->logout();
        $this->get(URL::signedRoute('email.unsubscribe', ['user' => $first->id]))->assertOk()->assertSee('Done, '.$first->firstName());
        $this->assertTrue($first->fresh()->email_opt_out);
        $this->get(route('email.unsubscribe', ['user' => $first->id]))->assertForbidden();
        $this->actingAs($first)->put(route('profile.update'), [
            'full_name' => $first->full_name, 'email' => $first->email, 'phone_number' => $first->phone_number,
            'member_type' => 'student', 'team' => 'volunteerism', 'email_news' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($first->fresh()->email_opt_out);

        $this->actingAs(User::factory()->create())->get(route('exco.announcements.index'))->assertForbidden();
    }

    public function test_email_editor_keeps_formatting_drops_scripts_and_takes_pictures(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Storage::fake('uploads');
        $mt = User::factory()->admin()->create();
        $member = User::factory()->create(['full_name' => 'Aina Sofea']);

        $up = $this->actingAs($mt)->postJson(route('exco.announcements.image'), ['image' => \Illuminate\Http\UploadedFile::fake()->image('turtle.jpg', 2400, 1600)])->assertOk();
        $this->assertStringContainsString('/uploads/email/', $up->json('url'));
        $stored = collect(\Illuminate\Support\Facades\Storage::disk('uploads')->allFiles('email'))->first();
        $this->assertSame(1200, getimagesizefromstring(\Illuminate\Support\Facades\Storage::disk('uploads')->get($stored))[0], 'shrunk to email width');
        $this->actingAs($mt)->postJson(route('exco.announcements.image'), ['image' => \Illuminate\Http\UploadedFile::fake()->create('notes.pdf', 10)])->assertStatus(422);

        $body = '<h2 style="text-align:center">Hello {name}</h2><p><strong>Bold</strong> <span style="color:#c0392b">red</span></p>'
            .'<script>alert(1)</script><p><a href="javascript:alert(1)">bad</a></p><img src="'.$up->json('url').'" onerror="alert(1)">';
        $html = (string) $this->actingAs($mt)->post(route('exco.announcements.preview'), ['subject' => 'Hi', 'body' => $body])->assertOk()->getContent();
        $this->assertStringContainsString('Hello '.$mt->firstName(), $html);
        $this->assertMatchesRegularExpression('#<strong[^>]*>Bold</strong>#', $html);
        $this->assertMatchesRegularExpression('/color: ?#c0392b/', $html);
        $this->assertStringContainsString($up->json('url'), $html);
        $this->assertStringNotContainsString('alert(1)', $html);

        $this->actingAs($mt)->postJson(route('exco.announcements.preview'), ['subject' => 'Hi', 'body' => '<p> </p>'])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($mt)->post(route('exco.announcements.store'), ['subject' => 'Hi {name}', 'body' => $body, 'action' => 'send'])->assertSessionHas('success');
        Notification::assertSentTo($member, ClubAnnouncement::class, function ($n) use ($member) {
            $mail = (string) $n->toMail($member)->render();

            return str_contains($mail, 'Hello Aina') && ! str_contains($mail, '<script');
        });
    }

    public function test_registered_members_get_one_reminder_before_the_event(): void
    {
        Notification::fake();
        $soon = Program::factory()->create(['starts_at' => now()->addHours(20), 'title' => 'Turtle Night']);
        $later = Program::factory()->create(['starts_at' => now()->addDays(4)]);
        $tooClose = Program::factory()->create(['starts_at' => now()->addMinutes(40)]);
        [$a, $b, $c, $late] = User::factory()->count(4)->create();
        $reg = fn ($u, $p, $status = 'registered', $at = null) => ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $u->id, 'status' => $status, 'registered_at' => $at ?? now()->subDays(2)]);
        $reg($a, $soon);
        $reg($b, $soon, 'cancelled');
        $reg($c, $later);
        $reg($c, $tooClose);
        $reg($late, $soon, 'registered', now()->subMinutes(5));

        $this->artisan('ycc:event-reminders')->assertSuccessful();
        Notification::assertSentTo($a, EventReminder::class, function ($n) use ($a) {
            $mail = $n->toMail($a);

            return str_contains($mail->subject, 'Turtle Night') && str_contains((string) $mail->render(), 'Can');
        });
        Notification::assertNotSentTo([$b, $c, $late], EventReminder::class);
        Notification::assertSentTimes(EventReminder::class, 1);

        // Never twice, unless the event moves to a new time.
        $this->artisan('ycc:event-reminders');
        Notification::assertSentTimes(EventReminder::class, 1);

        $mt = User::factory()->admin()->create();
        $this->actingAs($mt)->put(route('admin.settings.reminders'), ['event_reminders' => '0', 'event_reminder_hours' => 24])->assertSessionHas('success');
        $soon->registrations()->update(['reminded_at' => null]);
        $this->artisan('ycc:event-reminders');
        Notification::assertSentTimes(EventReminder::class, 1);
        $this->actingAs($mt)->put(route('admin.settings.reminders'), ['event_reminders' => '1', 'event_reminder_hours' => 48])->assertSessionHas('success');
        $this->artisan('ycc:event-reminders');
        Notification::assertSentTimes(EventReminder::class, 2);
        $this->actingAs($mt)->get(route('admin.settings.edit'))->assertSee('48 hours before');
    }

    public function test_publishing_an_event_can_email_members_with_the_poster(): void
    {
        Notification::fake();
        $mt = User::factory()->admin()->create(['team' => 'leadership']);
        $volunteers = User::factory()->count(3)->create(['team' => 'volunteerism']);
        $other = User::factory()->create(['team' => 'multimedia']);
        $payload = [
            'title' => 'Mangrove Day', 'description' => 'Planting.', 'category' => 'talk', 'venue' => 'Setiu',
            'starts_at' => now()->addDays(7)->format('Y-m-d\TH:i'), 'counts_for_activity' => '1',
            'announce' => '1', 'announce_team' => 'volunteerism', 'announce_note' => "Free transport at 7:30 AM.\n\nBring a hat.",
        ];

        // A draft is never emailed.
        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'status' => 'draft'])->assertSessionHasNoErrors();
        Notification::assertNothingSent();

        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'status' => 'published'])
            ->assertSessionHas('success', 'Event published. Members can register now. Emailed 3 members.');
        $program = Program::where('status', 'published')->firstOrFail();
        $this->assertNotNull($program->announced_at);
        Notification::assertSentTo($volunteers, ClubAnnouncement::class);
        Notification::assertNotSentTo([$other, $mt], ClubAnnouncement::class);
        $member = $volunteers->first();
        Notification::assertSentTo($member, ClubAnnouncement::class, function ($n) use ($member) {
            $html = (string) $n->toMail($member)->render();

            return $n->toMail($member)->subject === 'New event: Mangrove Day' && str_contains($html, 'Free transport at 7:30 AM.')
                && str_contains($html, 'View and register');
        });

        // Editing without ticking the box doesn't email again; the form shows it was sent.
        $this->actingAs($mt)->put(route('exco.programs.update', $program), [...$payload, 'status' => 'published', 'announce' => '0'])->assertSessionHas('success', 'Event updated.');
        Notification::assertSentTimes(ClubAnnouncement::class, 3);
        $this->actingAs($mt)->get(route('exco.programs.edit', $program))->assertSee('Emailed to')->assertSee('Email members again');
        $this->actingAs($mt)->get(route('exco.announcements.index'))->assertSee('New event: Mangrove Day');
    }
}
