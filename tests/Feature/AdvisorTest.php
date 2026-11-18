<?php

namespace Tests\Feature;

use App\Models\MeritEntry;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\ActivityStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The advisor (Penasihat) role: sees the MT side, changes nothing, not counted as a member. */
class AdvisorTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdvisor(): User
    {
        $super = User::factory()->superadmin()->create();
        $uzair = User::factory()->create(['full_name' => 'Mohd Uzair bin Rusli', 'team' => 'leadership']);
        $p = Program::factory()->create(['starts_at' => now()->subDays(3)]);
        ProgramRegistration::create(['program_id' => $p->id, 'user_id' => $uzair->id, 'status' => 'attended', 'registered_at' => now()->subDays(4)]);
        app(ActivityStatusService::class)->refreshUser($uzair);
        $this->assertSame(5, (int) MeritEntry::where('user_id', $uzair->id)->sum('points'));

        $this->actingAs($super)->put(route('admin.users.role', $uzair), ['role' => 'advisor'])
            ->assertSessionHas('success', 'Mohd Uzair bin Rusli is now Advisor (view-only).');

        return $uzair->fresh();
    }

    public function test_making_someone_advisor_takes_them_out_of_member_numbers_and_merit(): void
    {
        $uzair = $this->makeAdvisor();
        User::factory()->count(2)->create();

        $this->assertSame('staff', $uzair->member_type);
        $this->assertNull($uzair->team);
        $this->assertFalse($uzair->hasActivityStatus());
        $this->assertSame(0, MeritEntry::where('user_id', $uzair->id)->count());
        $this->assertFalse($uzair->earnsMerit());

        $club = $this->actingAs($uzair)->get(route('portal'))->assertOk()
            ->assertSee('Advisor view')->assertDontSee('Pick your YCC team')->viewData('club');
        $this->assertSame(2, $club['total'], 'advisors are not counted as members');
        $this->assertFalse($this->actingAs($uzair)->get('/exco/members?status=all')->assertOk()->viewData('members')->contains('id', $uzair->id), 'advisors are not in the members list');
        $this->actingAs($uzair)->get(route('mt.index'))->assertOk()->assertSee('Penasihat');
    }

    public function test_advisor_can_see_the_mt_pages(): void
    {
        $uzair = $this->makeAdvisor();
        $member = User::factory()->create();
        $program = Program::factory()->create(['starts_at' => now()->subHour()]);

        foreach (['/exco', '/exco/members', "/exco/members/{$member->id}", '/exco/approvals', '/exco/programs', "/exco/programs/{$program->id}/attendance",
            '/exco/emails', '/admin/analytics', '/admin/activity', '/exco/members/export'] as $url) {
            $this->actingAs($uzair)->get($url)->assertOk();
        }
        $this->actingAs($uzair)->get('/exco')->assertSee('View-only.')->assertSee('Advisor · view-only');
        $this->assertFalse($this->actingAs($uzair)->get("/exco/programs/{$program->id}/attendance")->viewData('editable'));
    }

    public function test_advisor_cannot_change_anything(): void
    {
        $uzair = $this->makeAdvisor();
        $member = User::factory()->create();
        $pending = User::factory()->create(['account_status' => 'pending']);
        $program = Program::factory()->create(['starts_at' => now()->subHour()]);
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $member->id, 'status' => 'registered', 'registered_at' => now()->subDay()]);

        $this->actingAs($uzair)->post(route('exco.approvals.approve', $pending))->assertForbidden();
        $this->actingAs($uzair)->post(route('exco.merit.store', $member), ['kind' => 'merit', 'points' => 5, 'reason' => 'Nice work'])->assertForbidden();
        $this->actingAs($uzair)->postJson(route('exco.attendance.update', $program), ['user_id' => $member->id, 'status' => 'attended'])->assertForbidden();
        $this->actingAs($uzair)->get(route('exco.programs.create'))->assertForbidden();
        $this->actingAs($uzair)->get(route('exco.programs.edit', $program))->assertForbidden();
        $this->actingAs($uzair)->post(route('exco.announcements.store'), ['subject' => 'Hi', 'body' => '<p>Hi</p>', 'action' => 'send'])->assertForbidden();
        $this->actingAs($uzair)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($uzair)->get(route('admin.users.index'))->assertForbidden();

        $this->assertSame('pending', $pending->fresh()->account_status);
        $this->assertSame('registered', $program->registrations()->first()->status);

        // Normal MT and members are unaffected: MT still edit, members still can't see the activity log.
        $this->actingAs(User::factory()->admin()->create())->get(route('exco.programs.create'))->assertOk();
        $this->actingAs($member)->get(route('admin.activity'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.activity'))->assertForbidden();
    }

    public function test_advisor_profile_and_positions(): void
    {
        $uzair = $this->makeAdvisor();
        $super = User::where('role', 'superadmin')->first();

        $this->actingAs($uzair)->get(route('profile.edit'))->assertOk()->assertDontSee('Pick your YCC team')->assertDontSee('name="team"', false);
        $this->actingAs($uzair)->put(route('profile.update'), [
            'full_name' => 'Prof. Madya Dr. Mohd Uzair bin Rusli', 'email' => $uzair->email, 'phone_number' => '0183644601',
            'member_type' => 'staff', 'faculty' => 'Institute of Oceanography and Environment',
        ])->assertSessionHasNoErrors();
        $this->assertSame('staff', $uzair->fresh()->member_type);

        $this->actingAs($super)->put(route('admin.users.position', $uzair), ['position' => 'presiden'])->assertSessionHas('error');
        $this->actingAs($super)->get(route('admin.users.index'))->assertSee('1 advisor')->assertSee('Penasihat / staff');

        // Students can't pick staff at sign-up.
        auth()->logout();
        $this->post('/register', ['full_name' => 'Sneaky', 'matric_no' => 'S11111', 'email' => 's@x.com', 'phone_number' => '0123456789',
            'member_type' => 'staff', 'team' => 'leadership', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'pdpa_consent' => '1'])
            ->assertSessionHasErrors('member_type');

        // Switching back to member makes them a student again.
        $this->actingAs($super)->put(route('admin.users.role', $uzair), ['role' => 'member']);
        $this->assertSame('student', $uzair->fresh()->member_type);
    }

    public function test_mt_can_email_just_the_advisor(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $uzair = $this->makeAdvisor();
        $mt = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->assertSame(1, $this->actingAs($mt)->getJson(route('exco.announcements.count', ['roles' => ['advisor']]))->json('count'));
        $this->assertSame(2, $this->actingAs($mt)->getJson(route('exco.announcements.count', ['roles' => ['advisor', 'member']]))->json('count'));
        $this->actingAs($mt)->post(route('exco.announcements.store'), ['subject' => 'Welcome to YCC Hub', 'body' => '<p>Dear Prof.</p>', 'roles' => ['advisor'], 'action' => 'send'])
            ->assertSessionHas('success', 'Sent to 1 member.');
        \Illuminate\Support\Facades\Notification::assertSentTo($uzair, \App\Notifications\ClubAnnouncement::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo([$mt, $member], \App\Notifications\ClubAnnouncement::class);
        $this->assertSame('Advisors', \App\Models\Announcement::first()->audience_label);
    }
}
