<?php

namespace Tests\Feature;

use App\Models\MtTerm;
use App\Models\User;
use App\Services\MtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MT positions, team leaders, the Our MT page and session handover. */
class MtDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_2026_2027_list_is_loaded_and_members_see_who_to_message(): void
    {
        $this->assertSame(10, MtTerm::where('session', '2026/2027')->count());

        $this->actingAs(User::factory()->create())->get(route('mt.index'))->assertOk()
            ->assertSee('Sesi 2026/2027')->assertSee('Prof. Madya Dr. Mohd Uzair bin Rusli')
            ->assertSee('Hazirah binti Mohd Azhar')->assertSee('Bendahari')->assertSee('Payments, fees, claims and receipts')
            ->assertSee('Leads the Multimedia Team')->assertSee('Not on YCC Hub yet')
            ->assertDontSee('Handover to a new session');
    }

    public function test_someone_on_the_list_becomes_mt_when_approved(): void
    {
        $mt = User::factory()->admin()->create();
        $setiausaha = User::factory()->create(['matric_no' => 'S79880', 'account_status' => 'pending', 'full_name' => 'Mimie Farhana', 'phone_number' => '0138154223']);

        $this->actingAs($mt)->get(route('exco.approvals.index'))->assertSee('On the MT list as Setiausaha');
        $this->actingAs($mt)->post(route('exco.approvals.approve', $setiausaha))->assertSessionHas('success');

        $setiausaha->refresh();
        $this->assertSame('admin', $setiausaha->role);
        $this->assertSame('Setiausaha', $setiausaha->positionLabel());
        $this->actingAs(User::factory()->create())->get(route('mt.index'))->assertSee('WhatsApp Mimie')->assertSee('wa.me/60138154223', false);
        $this->assertDatabaseHas('activity_logs', ['action' => 'member.role', 'description' => 'Mimie Farhana joined the MT as Setiausaha (on the MT list)']);
    }

    public function test_superadmin_gives_positions_in_roles_and_access(): void
    {
        $super = User::factory()->superadmin()->create();
        $first = User::factory()->create(['full_name' => 'First Person']);
        $second = User::factory()->create(['full_name' => 'Second Person', 'team' => 'volunteerism']);

        $this->actingAs($super)->get(route('admin.users.index'))->assertOk()->assertSee('Position 2026/2027')->assertSee('(now Nur Adrieana)');
        $this->actingAs($super)->put(route('admin.users.position', $first), ['position' => 'ketua_multimedia'])->assertSessionHas('success');
        $this->assertSame('admin', $first->fresh()->role);
        $this->assertSame('Ketua Multimedia', $first->fresh()->positionLabel());

        // Moving the position to someone else takes it off the first person.
        $this->actingAs($super)->put(route('admin.users.position', $second), ['position' => 'ketua_multimedia'])
            ->assertSessionHas('success', 'Second Person is now Ketua Multimedia. First Person no longer holds it.');
        app(MtService::class)->flush();
        $this->assertNull($first->fresh()->positionLabel());
        $this->assertSame(1, MtTerm::where('position', 'ketua_multimedia')->count());

        $this->actingAs($super)->get('/exco/members?status=all')->assertSee('Ketua Multimedia');
        $this->actingAs($super)->get(route('admin.activity'))->assertSee('Made Second Person Ketua Multimedia for 2026/2027');
        $this->actingAs($second)->put(route('admin.users.position', $first), ['position' => 'presiden'])->assertForbidden();
    }

    public function test_team_members_see_their_leader_and_leaders_see_their_team(): void
    {
        $super = User::factory()->superadmin()->create();
        $leader = User::factory()->create(['full_name' => 'Ashikin Leader', 'team' => 'volunteerism', 'phone_number' => '0113655502']);
        $member = User::factory()->create(['team' => 'volunteerism']);
        $other = User::factory()->create(['team' => 'multimedia']);
        $this->actingAs($super)->put(route('admin.users.position', $leader), ['position' => 'ketua_sukarelawan']);

        $this->actingAs($member)->get(route('portal'))->assertSee('Your team leader')->assertSee('Ashikin Leader')->assertSee('wa.me/60113655502', false);
        $this->actingAs($other)->get(route('portal'))->assertDontSee('Ashikin Leader');
        $this->actingAs($leader->fresh())->get(route('portal'))->assertSee('You lead the Volunteerism Team')->assertSee('2 members in your team');
        $this->actingAs($leader->fresh())->get(route('exco.members.index'))->assertSee('My team');
    }

    public function test_handover_keeps_the_old_mt_on_record(): void
    {
        $super = User::factory()->superadmin()->create();
        $presiden = User::factory()->create(['full_name' => 'Old Presiden']);
        $this->actingAs($super)->put(route('admin.users.position', $presiden), ['position' => 'presiden']);

        $this->actingAs($super)->get(route('mt.index'))->assertSee('Handover to a new session')->assertSee('2027/2028');
        $this->actingAs($super)->post(route('admin.mt.session'), ['session' => '2026/2027'])->assertSessionHasErrors('session');
        $this->actingAs($super)->post(route('admin.mt.session'), ['session' => '2027/2028', 'demote' => '1'])->assertSessionHas('success');

        $this->assertSame('member', $presiden->fresh()->role);
        $this->assertNull($presiden->fresh()->positionLabel());
        $this->actingAs($super)->get(route('mt.index'))->assertSee('Sesi 2027/2028')->assertSee('Past MT')->assertSee('Sesi 2026/2027')->assertSee('Old Presiden');
        $this->actingAs($presiden)->get(route('profile.edit'))->assertSee('Your YCC record')->assertSee('Majlis Tertinggi, Sesi 2026/2027');
        $this->actingAs($super)->get(route('exco.members.show', $presiden))->assertSee('MT record');
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.mt.session'), ['session' => '2028/2029'])->assertForbidden();
    }

    public function test_public_page_shows_the_mt_without_phone_numbers(): void
    {
        $super = User::factory()->superadmin()->create();
        $presiden = User::factory()->create(['full_name' => 'HAZIRAH BINTI MOHD AZHAR', 'phone_number' => '0127259840']);
        $this->actingAs($super)->put(route('admin.users.position', $presiden), ['position' => 'presiden']);
        auth()->logout();

        $this->get('/')->assertOk()->assertSee('Meet the MT')->assertSee('Hazirah binti Mohd Azhar')->assertSee('Penasihat')
            ->assertDontSee('0127259840')->assertDontSee('wa.me/60127259840', false);

        $this->actingAs($super)->put(route('admin.mt.settings'), ['mt_advisor' => 'Prof. Madya Dr. Mohd Uzair bin Rusli', 'mt_public' => '0'])->assertSessionHas('success');
        auth()->logout();
        $this->get('/')->assertOk()->assertDontSee('Meet the MT');
    }
}
