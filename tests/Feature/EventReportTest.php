<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ProgramCommittee;
use App\Models\ProgramFeedback;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The printable event report the MT submits after an event. */
class EventReportTest extends TestCase
{
    use RefreshDatabase;

    private function attend(Program $p, User $u, string $status = 'attended', ?string $method = 'qr'): void
    {
        ProgramRegistration::create([
            'program_id' => $p->id, 'user_id' => $u->id, 'status' => $status, 'registered_at' => now()->subDays(10),
            'checked_in_at' => $status === 'attended' ? $p->starts_at->copy()->addMinutes(5) : null,
            'checkin_method' => $status === 'attended' ? $method : null,
        ]);
    }

    public function test_report_shows_numbers_committee_feedback_and_first_timers(): void
    {
        $mt = User::factory()->admin()->create(['full_name' => 'Nur Aisyah binti Kamarul']);
        $old = Program::factory()->create(['starts_at' => now()->subDays(40)]);
        $event = Program::factory()->create([
            'title' => 'Mangrove Planting', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(4),
            'volunteer_hours' => null, 'committee_count' => 6,
        ]);

        $regular = User::factory()->create(['full_name' => 'Siti Regular', 'team' => 'leadership']);
        $newbie = User::factory()->create(['full_name' => 'Ali Newbie', 'team' => 'multimedia']);
        $noShow = User::factory()->create(['full_name' => 'Kamal Noshow']);
        $quitter = User::factory()->create(['full_name' => 'Zara Cancelled']);

        $this->attend($old, $regular);
        $this->attend($event, $regular);
        $this->attend($event, $newbie, 'attended', 'manual');
        $this->attend($event, $noShow, 'absent');
        $this->attend($event, $quitter, 'cancelled');

        ProgramCommittee::create(['program_id' => $event->id, 'user_id' => $regular->id, 'position' => 'Pengarah Program', 'sort' => 0]);
        ProgramCommittee::create(['program_id' => $event->id, 'user_id' => null, 'position' => 'AJK Multimedia', 'sort' => 1]);
        ProgramFeedback::create(['program_id' => $event->id, 'user_id' => $regular->id, 'rating' => 5, 'comment' => 'Loved the boat ride']);
        ProgramFeedback::create(['program_id' => $event->id, 'user_id' => $newbie->id, 'rating' => 4]);

        $res = $this->actingAs($mt)->get(route('exco.programs.report', $event))->assertOk()
            ->assertSee('EVENT REPORT')->assertSee('Mangrove Planting')
            ->assertSee('Pengarah Program')->assertSee('To be decided')
            ->assertSee('Loved the boat ride')->assertSee('4.5')
            ->assertSee('Siti Regular')->assertSee('Ali Newbie')
            ->assertDontSee('Kamal Noshow')->assertDontSee('Zara Cancelled')
            ->assertSee('Nur Aisyah binti Kamarul');

        $counts = $res->viewData('counts');
        $this->assertSame(3, $counts['on_list'], 'cancelled people are not on the list');
        $this->assertSame(2, $counts['attended']);
        $this->assertSame(1, $counts['absent']);
        $this->assertSame(1, $counts['cancelled']);
        $this->assertSame(1, $counts['qr']);
        $this->assertSame(1, $counts['manual']);
        $this->assertSame(67, $res->viewData('rate'));
        $this->assertEquals(8.0, $res->viewData('hoursTotal'), 'two attendees x four hours');
        $this->assertEquals([$newbie->id], $res->viewData('firstTimers')->all(), 'only the newcomer is a first-timer');
        $byTeam = $res->viewData('byTeam');
        ksort($byTeam);
        $this->assertSame(['Leadership Team' => 1, 'Multimedia Team' => 1], $byTeam);

        // No phone numbers or emails in a report that leaves the club.
        $res->assertDontSee($regular->phone_number)->assertDontSee($regular->email);
    }

    public function test_report_is_for_the_mt_and_advisor_only(): void
    {
        $event = Program::factory()->create(['starts_at' => now()->subDay()]);
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('exco.programs.report', $event))->assertForbidden();

        $advisor = User::factory()->create(['role' => 'advisor', 'member_type' => 'staff', 'team' => null]);
        $this->actingAs($advisor)->get(route('exco.programs.report', $event))->assertOk();
    }

    public function test_attendance_page_and_event_list_link_to_the_report(): void
    {
        $mt = User::factory()->admin()->create();
        $event = Program::factory()->create(['starts_at' => now()->subDay()]);
        $url = route('exco.programs.report', $event);

        $this->actingAs($mt)->get(route('exco.attendance.show', $event))->assertOk()->assertSee($url, false);
        $this->actingAs($mt)->get(route('exco.programs.index', ['tab' => 'past']))->assertOk()->assertSee($url, false);
        $this->actingAs($mt)->get(route('programs.show', $event))->assertOk()->assertSee($url, false);

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('programs.show', $event))->assertOk()->assertDontSee($url, false);
    }
}
