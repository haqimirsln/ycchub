<?php

namespace Tests\Feature;

use App\Models\ExportLog;
use App\Models\Program;
use App\Models\ProgramFeedback;
use App\Models\ProgramRegistration;
use App\Services\VolunteerHoursService;
use App\Models\User;
use App\Notifications\AccountReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YccHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        Program::factory()->daysFromNow(5)->create(['title' => 'Turtle Night Walk']);

        $this->get('/')->assertOk()->assertSee('Turtle Night Walk')->assertSee('young.conservationist');
    }

    public function test_self_registration_waits_for_approval(): void
    {
        $this->post('/register', [
            'full_name' => 'Ali bin Abu',
            'matric_no' => 's 12345',
            'email' => 'ALI@example.com',
            'phone_number' => '012-345 6789',
            'member_type' => 'student',
            'team' => 'multimedia',
            'faculty' => 'STEM Foundation',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'pdpa_consent' => '1',
        ])->assertRedirect(route('account.pending'));

        $user = User::where('matric_no', 'S12345')->firstOrFail();
        $this->assertSame('pending', $user->account_status);
        $this->assertSame('ali@example.com', $user->email);
        $this->assertSame('0123456789', $user->phone_number);
        $this->assertSame('multimedia', $user->team);
        $this->assertSame('STEM Foundation', $user->facultyName());

        $this->actingAs($user)->get('/portal')->assertRedirect(route('account.pending'));
        $program = Program::factory()->create();
        $this->actingAs($user)->post("/programs/{$program->id}/register")->assertRedirect(route('account.pending'));
    }

    public function test_login_with_matric_or_email(): void
    {
        $u = User::factory()->create(['matric_no' => 'S55555', 'email' => 'm@example.com']);

        $this->post('/login', ['login' => 's55555', 'password' => 'password'])->assertRedirect(route('home.redirect'));
        $this->assertAuthenticatedAs($u);
        $this->post('/logout');
        $this->post('/login', ['login' => 'M@example.com', 'password' => 'password'])->assertRedirect(route('home.redirect'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_suspended_users_cannot_log_in(): void
    {
        User::factory()->create(['matric_no' => 'S1', 'account_status' => 'suspended']);
        $this->post('/login', ['login' => 'S1', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_exco_approves_and_member_is_notified(): void
    {
        Notification::fake();
        $exco = User::factory()->exco()->create();
        $applicant = User::factory()->pending()->create();

        $this->actingAs($exco)->post(route('exco.approvals.approve', $applicant))->assertSessionHas('success');

        $applicant->refresh();
        $this->assertSame('approved', $applicant->account_status);
        $this->assertSame('new', $applicant->activity_status);
        $this->assertSame($exco->id, $applicant->reviewed_by);
        Notification::assertSentTo($applicant, AccountReviewed::class);
    }

    public function test_members_cannot_reach_exco_pages(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get('/exco')->assertForbidden();
        $this->actingAs($member)->get('/exco/members/export')->assertForbidden();
        $this->actingAs($member)->get('/admin/settings')->assertForbidden();

        // MT (admin) runs the club side, but roles and the activity log are superadmin only.
        $mt = User::factory()->admin()->create();
        $this->actingAs($mt)->get('/exco')->assertOk();
        $this->actingAs($mt)->get('/admin/settings')->assertOk();
        $this->actingAs($mt)->get('/admin/users')->assertForbidden();
        $this->actingAs($mt)->get('/admin/activity')->assertForbidden();
    }

    public function test_capacity_is_enforced_and_cancel_frees_a_seat(): void
    {
        $program = Program::factory()->daysFromNow(10)->create(['max_capacity' => 1]);
        [$a, $b] = User::factory()->count(2)->create();

        $this->actingAs($a)->postJson(route('programs.register', $program))->assertOk();
        $this->actingAs($b)->postJson(route('programs.register', $program))->assertStatus(422)->assertJson(['message' => 'Sorry, this event is full.']);
        $this->actingAs($a)->postJson(route('programs.register', $program))->assertStatus(422);

        $this->actingAs($a)->postJson(route('programs.cancel', $program))->assertOk();
        $this->actingAs($b)->postJson(route('programs.register', $program))->assertOk();
        // Re-registering after cancelling reuses the same row.
        $this->assertSame(1, ProgramRegistration::where('program_id', $program->id)->where('user_id', $a->id)->count());
    }

    public function test_cannot_cancel_after_start(): void
    {
        $program = Program::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);
        $u = User::factory()->create();
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $u->id, 'status' => 'registered', 'registered_at' => now()->subDay()]);

        $this->actingAs($u)->postJson(route('programs.cancel', $program))->assertStatus(422);
    }

    public function test_attendance_marking_updates_status(): void
    {
        $exco = User::factory()->exco()->create();
        $member = User::factory()->joinedDaysAgo(200)->create(['activity_status' => 'inactive']);
        $program = Program::factory()->create(['starts_at' => now()->subHours(2), 'ends_at' => now()->addHour()]);
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $member->id, 'status' => 'registered', 'registered_at' => now()->subDay()]);

        $this->actingAs($exco)->postJson(route('exco.attendance.update', $program), ['user_id' => $member->id, 'status' => 'attended'])
            ->assertOk()->assertJsonPath('counts.attended', 1);

        $member->refresh();
        $this->assertSame('active', $member->activity_status);
        $this->assertSame(1, $member->total_attended);
    }

    public function test_attendance_locks_after_window(): void
    {
        $exco = User::factory()->exco()->create();
        $member = User::factory()->create();
        $program = Program::factory()->create(['starts_at' => now()->subDays(30)]);
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $member->id, 'status' => 'registered', 'registered_at' => now()->subDays(31)]);

        $this->actingAs($exco)->postJson(route('exco.attendance.update', $program), ['user_id' => $member->id, 'status' => 'attended'])->assertStatus(422);

        $super = User::factory()->superadmin()->create();
        $this->actingAs($super)->postJson(route('exco.attendance.update', $program), ['user_id' => $member->id, 'status' => 'attended'])->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'event.attendance', 'user_id' => $super->id]);
    }

    public function test_qr_checkin_requires_open_checkin_and_counts_walk_ins(): void
    {
        $member = User::factory()->create();
        $program = Program::factory()->create(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHours(2)]);

        $this->actingAs($member)->post(route('checkin.store', $program->checkin_code))->assertSessionHas('error');

        $program->forceFill(['checkin_open' => true])->save();
        $this->actingAs($member)->post(route('checkin.store', $program->checkin_code))->assertRedirect();

        $reg = ProgramRegistration::where('program_id', $program->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertSame('attended', $reg->status);
        $this->assertSame('qr', $reg->checkin_method);
        $this->assertSame('active', $member->fresh()->activity_status);
    }

    public function test_cancelled_programs_stop_counting(): void
    {
        $exco = User::factory()->exco()->create();
        $member = User::factory()->joinedDaysAgo(200)->create();
        $program = Program::factory()->create(['starts_at' => now()->subDays(5)]);
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $member->id, 'status' => 'attended', 'registered_at' => now()->subDays(6)]);
        app(\App\Services\ActivityStatusService::class)->refreshUser($member);
        $this->assertSame('active', $member->fresh()->activity_status);

        $this->actingAs($exco)->post(route('exco.programs.cancel', $program));
        $this->assertSame('inactive', $member->fresh()->activity_status);
    }

    public function test_export_is_logged_and_contains_members(): void
    {
        $exco = User::factory()->exco()->create();
        User::factory()->joinedDaysAgo(200)->create(['full_name' => 'Quiet Member', 'activity_status' => 'inactive']);

        $res = $this->actingAs($exco)->get(route('exco.members.export', ['status' => 'inactive']));
        $res->assertOk();
        $this->assertStringContainsString('Quiet Member', $res->streamedContent());
        $this->assertSame(1, ExportLog::count());
    }

    public function test_exco_can_create_program(): void
    {
        $exco = User::factory()->exco()->create();

        $this->actingAs($exco)->post(route('exco.programs.store'), [
            'title' => 'Reef Check',
            'description' => 'Survey.',
            'category' => 'coral_marine',
            'venue' => 'Pulau Bidong',
            'starts_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'max_capacity' => 15,
            'status' => 'published',
            'counts_for_activity' => '1',
        ])->assertRedirect();

        $p = Program::where('title', 'Reef Check')->firstOrFail();
        $this->assertSame(15, $p->max_capacity);
        $this->assertNotEmpty($p->checkin_code);
    }

    public function test_last_superadmin_cannot_be_demoted(): void
    {
        $admin = User::factory()->superadmin()->create();
        $other = User::factory()->superadmin()->create();

        $this->actingAs($admin)->put(route('admin.users.role', $other), ['role' => 'admin'])->assertSessionHas('success');
        $this->assertSame('admin', $other->fresh()->role);
        $this->actingAs($other->fresh())->get('/admin/users')->assertForbidden();
        $this->actingAs($admin)->put(route('admin.users.role', $admin), ['role' => 'member'])->assertSessionHas('error');
        $this->assertDatabaseHas('activity_logs', ['action' => 'member.role', 'user_id' => $admin->id]);
    }

    public function test_installer_is_hidden_without_key_and_after_install(): void
    {
        config(['ycc.install_key' => null]);
        $this->get('/install')->assertNotFound();

        config(['ycc.install_key' => 'secret-key']);
        @unlink(storage_path('app/installed.lock'));
        $this->get('/install')->assertOk();

        User::factory()->admin()->create();
        $this->get('/install')->assertNotFound();
    }

    public function test_installer_creates_admin_and_demo_data(): void
    {
        config(['ycc.install_key' => 'secret-key']);
        @unlink(storage_path('app/installed.lock'));

        $this->post('/install', [
            'install_key' => 'wrong', 'full_name' => 'Club Admin', 'matric_no' => 'staff01', 'email' => 'a@umt.edu.my',
            'phone_number' => '0123456789', 'password' => 'longpass123', 'password_confirmation' => 'longpass123',
        ])->assertSessionHasErrors('install_key');

        $this->post('/install', [
            'install_key' => 'secret-key', 'full_name' => 'Club Admin', 'matric_no' => 'staff01', 'email' => 'a@umt.edu.my',
            'phone_number' => '0123456789', 'password' => 'longpass123', 'password_confirmation' => 'longpass123', 'seed_demo' => '1',
        ])->assertRedirect(route('login', ['installed' => 1]));

        $admin = User::where('matric_no', 'STAFF01')->firstOrFail();
        $this->assertTrue($admin->isAdmin() && $admin->isApproved());
        $this->assertGreaterThan(5, Program::count());
        $this->assertSame(2, User::where('activity_status', 'at_risk')->count());
        $this->get('/install')->assertNotFound();
        @unlink(storage_path('app/installed.lock'));
    }

    public function test_analytics_lists_members_by_status_for_mt_only(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->joinedDaysAgo(200)->create(['full_name' => 'Gone Member', 'faculty' => 'FPM', 'year_of_study' => 3]);
        $slipping = User::factory()->joinedDaysAgo(200)->create(['full_name' => 'Almost Gone', 'faculty' => 'FSSM']);
        $p = Program::factory()->create(['starts_at' => now()->subDays(55)]);
        ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $slipping->id, 'status' => 'attended', 'registered_at' => now()->subDays(56)]);
        app(\App\Services\ActivityStatusService::class)->refreshAll();

        $this->actingAs(User::factory()->create())->get('/admin/analytics')->assertForbidden();

        $res = $this->actingAs($admin)->get('/admin/analytics')->assertOk();
        $res->assertSee('Gone Member')->assertSee('Almost Gone')->assertSee('Faculty of Maritime Studies');
        $this->assertSame(1, $res->viewData('slipping')->count());
        $this->assertSame(1, $res->viewData('byStatus')['inactive']->where('full_name', 'Gone Member')->count());

        $filtered = $this->actingAs($admin)->get('/admin/analytics?faculty=FPM')->assertOk();
        $filtered->assertSee('Gone Member')->assertDontSee('Almost Gone');
    }

    public function test_registration_needs_a_team_and_takes_any_faculty_and_alumni(): void
    {
        $base = ['full_name' => 'Test Person', 'matric_no' => 'S99999', 'email' => 't@example.com', 'phone_number' => '0123456789',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'pdpa_consent' => '1', 'member_type' => 'student'];

        $this->post('/register', [...$base, 'faculty' => 'Faculty of Ocean Engineering Technology'])->assertSessionHasErrors('team');
        $this->post('/register', [...$base, 'team' => 'circus'])->assertSessionHasErrors('team');
        $this->post('/register', [...$base, 'team' => 'leadership', 'faculty' => 'Faculty of Ocean Engineering Technology'])->assertRedirect(route('account.pending'));
        $this->assertSame('Faculty of Ocean Engineering Technology', User::where('matric_no', 'S99999')->first()->facultyName());
        // Old code values saved before faculty became free text still show the full name.
        $this->assertSame('Faculty of Computer Science and Mathematics', User::factory()->make(['faculty' => 'FSKM'])->facultyName());

        auth()->logout();
        $this->post('/register', [...$base, 'matric_no' => 'S88888', 'email' => 'old@example.com', 'member_type' => 'alumni', 'team' => 'volunteerism', 'year_of_study' => '3'])
            ->assertRedirect(route('account.pending'));
        $alumni = User::where('matric_no', 'S88888')->first();
        $this->assertTrue($alumni->isAlumni());
        $this->assertNull($alumni->year_of_study);
        $this->assertDatabaseHas('activity_logs', ['action' => 'member.registered', 'user_id' => $alumni->id]);
    }

    public function test_every_page_renders_for_superadmin(): void
    {
        $admin = User::factory()->superadmin()->create();
        $member = User::factory()->create();
        $program = Program::factory()->create(['starts_at' => now()->subHour()]);
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $member->id, 'status' => 'attended', 'registered_at' => now()->subDay()]);

        foreach (['/portal', '/programs', '/programs?tab=past', "/programs/{$program->id}", '/profile', '/exco', '/exco/members', '/exco/members?status=all',
            "/exco/members/{$member->id}", '/exco/approvals', '/exco/programs', '/exco/programs?tab=past', '/exco/programs/create',
            "/exco/programs/{$program->id}/edit", "/exco/programs/{$program->id}/attendance", "/exco/programs/{$program->id}/qr",
            "/checkin/{$program->checkin_code}", '/admin/users', '/admin/activity', '/admin/settings', '/admin/settings/exports', '/admin/analytics', '/merit'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_member_can_add_and_remove_profile_picture(): void
    {
        Storage::fake('uploads');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('me.jpg', 900, 600),
        ])->assertSessionHasNoErrors()->assertRedirect();

        Storage::disk('uploads')->assertExists($user->avatarPath());
        [$w, $h] = getimagesizefromstring(Storage::disk('uploads')->get($user->avatarPath()));
        $this->assertSame([400, 400], [$w, $h]);

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($user)->delete('/profile/photo')->assertRedirect();
        Storage::disk('uploads')->assertMissing($user->avatarPath());
    }

    public function test_poster_gets_a_small_copy_for_lists(): void
    {
        $program = Program::factory()->create(['poster_path' => 'posters/test/poster.jpg']);
        $dir = public_path('uploads/posters/test');
        @mkdir($dir, 0755, true);
        imagejpeg(imagecreatetruecolor(1600, 2000), $dir.'/poster.jpg');

        try {
            $this->assertStringEndsWith('uploads/posters/thumbs/test/poster.jpg', $program->posterThumbUrl());
            [$w] = getimagesize(public_path('uploads/'.$program->posterThumbPath()));
            $this->assertSame(640, $w);

            $program->deletePosterFiles();
            $this->assertFileDoesNotExist($dir.'/poster.jpg');
            $this->assertFileDoesNotExist(public_path('uploads/'.$program->posterThumbPath()));
        } finally {
            @unlink($dir.'/poster.jpg');
            @unlink(public_path('uploads/posters/thumbs/test/poster.jpg'));
            @rmdir($dir);
            @rmdir(public_path('uploads/posters/thumbs/test'));
        }
    }

    public function test_app_can_be_installed(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'YCC Hub')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', route('portal'));

        $sw = $this->get('/sw.js')->assertOk();
        $this->assertStringContainsString('application/javascript', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString(route('pwa.offline'), $sw->getContent());

        $this->get('/offline')->assertOk()->assertSee("You're offline", false);
        $this->get('/get-the-app')->assertOk()->assertSee('Add to Home Screen')->assertSee('Install app');
        $this->get('/')->assertSee('manifest.webmanifest');
    }

    private function programPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Plastic Talk',
            'description' => 'Talk.',
            'category' => 'talk',
            'starts_at' => now()->addDays(3)->setTime(20, 0)->format('Y-m-d\TH:i'),
            'status' => 'published',
            'counts_for_activity' => '1',
        ];
    }

    public function test_in_person_program_keeps_its_map_pin(): void
    {
        $exco = User::factory()->exco()->create();
        $this->actingAs($exco)->post(route('exco.programs.store'), $this->programPayload([
            'location_type' => 'in_person', 'venue' => 'Setiu Wetlands', 'latitude' => '5.6573', 'longitude' => '102.7392',
            'meeting_url' => 'https://umt.webex.com/meet/x',
        ]))->assertSessionHasNoErrors();

        $p = Program::where('title', 'Plastic Talk')->firstOrFail();
        $this->assertTrue($p->hasPin());
        $this->assertNull($p->meeting_url, 'in-person programs drop any meeting link');
        $this->assertStringContainsString('query=5.6573%2C102.7392', $p->mapUrl());

        $this->actingAs($exco)->post(route('exco.programs.store'), $this->programPayload(['title' => 'No venue', 'location_type' => 'in_person']))
            ->assertSessionHasErrors('venue');
    }

    public function test_online_program_link_is_only_for_registered_members_near_the_start(): void
    {
        $exco = User::factory()->exco()->create();
        $this->actingAs($exco)->post(route('exco.programs.store'), $this->programPayload([
            'location_type' => 'online', 'venue' => 'ignored', 'latitude' => '5.1', 'longitude' => '103.1',
            'meeting_url' => 'https://umt.webex.com/meet/ycc', 'meeting_info' => 'Password: turtle',
        ]))->assertSessionHasNoErrors();

        $p = Program::where('title', 'Plastic Talk')->firstOrFail();
        $this->assertTrue($p->isOnline());
        $this->assertSame('Online', $p->venue);
        $this->assertNull($p->latitude);
        $this->assertSame('Online on Webex', $p->whereLabel());
        $this->assertNull($p->mapUrl());

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('programs.show', $p))
            ->assertSee('Register to get the join link')->assertDontSee('umt.webex.com/meet/ycc')->assertDontSee('Password: turtle');

        ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $member->id, 'status' => 'registered', 'registered_at' => now()]);
        $this->actingAs($member)->get(route('programs.show', $p))
            ->assertSee('30 minutes before the start')->assertDontSee('umt.webex.com/meet/ycc');

        $this->travelTo($p->starts_at->copy()->subMinutes(10));
        $this->actingAs($member)->get(route('programs.show', $p))
            ->assertSee('Join on Webex')->assertSee('umt.webex.com/meet/ycc')->assertSee('Password: turtle');

        $this->travelTo($p->starts_at->copy()->addHours(6));
        $this->actingAs($member)->get(route('programs.show', $p))
            ->assertSee('This online session has ended')->assertDontSee('umt.webex.com/meet/ycc');
    }

    public function test_meeting_link_must_be_https(): void
    {
        $exco = User::factory()->exco()->create();
        $this->actingAs($exco)->post(route('exco.programs.store'), $this->programPayload([
            'location_type' => 'hybrid', 'venue' => 'DKU', 'meeting_url' => 'javascript:alert(1)',
        ]))->assertSessionHasErrors('meeting_url');
    }

    public function test_pasted_map_links_become_pins(): void
    {
        $exco = User::factory()->exco()->create();
        $this->actingAs($exco)->postJson(route('exco.programs.map-link'), [
            'link' => 'https://www.google.com/maps/place/Batu+Buruk/@5.32,103.15,15z/data=!3d5.3191!4d103.153',
        ])->assertOk()->assertExactJson(['lat' => 5.3191, 'lng' => 103.153]);

        $this->actingAs($exco)->postJson(route('exco.programs.map-link'), ['link' => '5.4097, 103.087'])
            ->assertOk()->assertJson(['lat' => 5.4097]);

        $this->actingAs($exco)->postJson(route('exco.programs.map-link'), ['link' => 'just some words'])->assertStatus(422);

        $this->actingAs(User::factory()->create())->postJson(route('exco.programs.map-link'), ['link' => '5.4, 103.0'])->assertForbidden();
    }

    public function test_admin_can_apply_database_updates_from_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Up to date');
        $this->actingAs($admin)->post(route('admin.settings.database'))->assertSessionHas('success', 'Database was already up to date.');
        $this->actingAs(User::factory()->create())->post(route('admin.settings.database'))->assertForbidden();
    }

    public function test_program_can_be_added_to_a_calendar(): void
    {
        $member = User::factory()->create();
        $p = Program::factory()->create([
            'title' => 'Reef, Check; Day', 'venue' => 'Pulau Bidong',
            'starts_at' => now()->addDays(5)->setTime(8, 0), 'ends_at' => now()->addDays(5)->setTime(12, 0),
        ]);

        $ics = $this->actingAs($member)->get(route('programs.calendar', $p))
            ->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $ics->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('SUMMARY:Reef\\, Check\\; Day (YCC)', $body);
        $this->assertStringContainsString('DTSTART:'.$p->starts_at->copy()->utc()->format('Ymd\THis\Z'), $body);
        $this->assertStringContainsString("\r\n", $body);

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?action=TEMPLATE', $p->googleCalendarUrl());
        $this->actingAs($member)->get(route('programs.show', $p))->assertSee('Add to calendar')->assertSee('Apple / Outlook');
    }

    public function test_online_calendar_entry_never_contains_the_meeting_link(): void
    {
        $p = Program::factory()->create(['location_type' => 'online', 'venue' => 'Online', 'meeting_url' => 'https://umt.webex.com/meet/secret']);
        $body = $this->actingAs(User::factory()->create())->get(route('programs.calendar', $p))->getContent();
        $this->assertStringNotContainsString('webex.com/meet/secret', $body);
        $this->assertStringNotContainsString('webex.com/meet/secret', $p->googleCalendarUrl());
        $this->assertStringContainsString('Online on Webex', $body);
    }

    public function test_only_attendees_can_rate_and_excos_see_the_average(): void
    {
        $p = Program::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(3)]);
        $went = User::factory()->create();
        $absent = User::factory()->create();
        ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $went->id, 'status' => 'attended', 'registered_at' => now()->subWeek()]);
        ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $absent->id, 'status' => 'absent', 'registered_at' => now()->subWeek()]);

        $this->actingAs($went)->get(route('programs.show', $p))->assertSee('How was it?');
        $this->actingAs($went)->post(route('programs.feedback', $p), ['rating' => 4, 'comment' => 'Fun day'])->assertSessionHas('success');
        $this->actingAs($went)->post(route('programs.feedback', $p), ['rating' => 5])->assertSessionHas('success');
        $this->assertSame(1, ProgramFeedback::count(), 'changing your rating updates it, no duplicates');
        $this->assertSame(5, ProgramFeedback::first()->rating);

        $this->actingAs($absent)->post(route('programs.feedback', $p), ['rating' => 1])->assertForbidden();
        $this->actingAs($went)->post(route('programs.feedback', $p), ['rating' => 9])->assertSessionHasErrors('rating');

        $this->actingAs(User::factory()->exco()->create())->get(route('programs.show', $p))
            ->assertSee('What members said')->assertSee('5.0')->assertDontSee($went->full_name.' rated');
        $this->actingAs($went)->get(route('portal'))->assertDontSee('How did it go?');
    }

    public function test_feedback_closes_after_thirty_days(): void
    {
        $p = Program::factory()->create(['starts_at' => now()->subDays(40)]);
        $u = User::factory()->create();
        ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $u->id, 'status' => 'attended', 'registered_at' => now()->subDays(45)]);
        $this->actingAs($u)->post(route('programs.feedback', $p), ['rating' => 5])->assertSessionHas('error');
        $this->assertSame(0, ProgramFeedback::count());
    }

    public function test_volunteer_hours_add_up_from_attended_programs(): void
    {
        $u = User::factory()->create();
        $start = now()->startOfYear()->addMonths(2)->setTime(8, 0);
        $short = Program::factory()->create(['starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(150)]);
        $trip = Program::factory()->create(['starts_at' => $start->copy()->addDays(7), 'ends_at' => $start->copy()->addDays(8), 'volunteer_hours' => 16]);
        $agm = Program::factory()->create(['starts_at' => $start->copy()->addDays(9), 'ends_at' => $start->copy()->addDays(9)->addHours(3), 'counts_for_activity' => false]);
        $missed = Program::factory()->create(['starts_at' => $start->copy()->addDays(10), 'ends_at' => $start->copy()->addDays(10)->addHours(5)]);
        foreach ([[$short, 'attended'], [$trip, 'attended'], [$agm, 'attended'], [$missed, 'absent']] as [$p, $status]) {
            ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $u->id, 'status' => $status, 'registered_at' => $start]);
        }

        $h = app(VolunteerHoursService::class)->forUser($u);
        $this->assertSame(18.5, $h['year']);
        $this->assertSame(2, $h['programs_year']);
        $this->assertSame('18.5', VolunteerHoursService::format($h['year']));

        $this->actingAs($u)->get(route('portal'))->assertSee('18.5 hours');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.analytics'))->assertSee('Top volunteers')->assertSee($u->full_name);
    }

    public function test_admins_get_a_to_do_strip_instead_of_a_member_status(): void
    {
        User::factory()->create(['account_status' => 'pending']);
        $super = User::factory()->superadmin()->create();
        $this->actingAs($super)->get(route('portal'))
            ->assertOk()->assertSee('Welcome back')->assertSee('1 sign-up to approve')
            ->assertDontSee('Welcome to YCC! Your status becomes Active');

        $mt = User::factory()->admin()->create();
        $this->actingAs($mt)->get(route('portal'))->assertSee('MT to-do')->assertSee('Welcome to YCC!');

        $this->actingAs(User::factory()->create())->get(route('portal'))->assertDontSee('MT to-do')->assertDontSee('sign-up to approve')
            ->assertSee('YCC right now');
    }

    public function test_emails_use_the_club_layout(): void
    {
        $u = User::factory()->create(['full_name' => 'Siti Nurhaliza', 'matric_no' => 'S99001']);
        $html = (string) (new AccountReviewed(true))->toMail($u)->render();
        $this->assertStringContainsString('Welcome aboard, Siti!', $html);
        $this->assertStringContainsString('S99001', $html);
        $this->assertStringContainsString('ycc-crest-192.png', $html);
        $this->assertStringContainsString(route('pwa.guide'), $html);

        $rejected = (string) (new AccountReviewed(false, 'Matric not found'))->toMail($u)->render();
        $this->assertStringContainsString('Matric not found', $rejected);

        $reset = (string) (new \Illuminate\Auth\Notifications\ResetPassword('tok'))->toMail($u)->render();
        $this->assertStringContainsString('Choose a new password', $reset);

        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.settings.test-mail'))->assertSessionHas('success');
        Notification::assertSentTo($admin, AccountReviewed::class);
    }
}
