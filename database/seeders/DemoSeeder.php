<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramFeedback;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\ActivityStatusService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data with dates relative to today, so the Active / At-Risk / Inactive /
 * New mix stays correct no matter when you seed. Every demo account's password
 * is "password". Does not use Faker, so it also works on a --no-dev install.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $excos = [
            ['EX1001', 'Nur Aisyah binti Kamarul', 'aisyah.exco@ycc.test', '0139876501', 'FSSM', 'Marine Biology', 3, 400],
            ['EX1002', 'Muhammad Hafiz bin Rahman', 'hafiz.exco@ycc.test', '0172233445', 'FSPA', 'Fisheries', 2, 380],
        ];

        //  matric, name, email, phone, faculty, programme, year, joined days ago
        $members = [
            ['S70011', 'Siti Nurhaliza binti Azman', 'siti@ycc.test', '0111234501', 'FSSM', 'Marine Biology', 2, 300],
            ['S70012', 'Ahmad Danial bin Roslan', 'danial@ycc.test', '0121234502', 'FTKK', 'Ocean Engineering', 3, 280],
            ['S70013', 'Tan Wei Ling', 'weiling@ycc.test', '0161234503', 'FSSM', 'Biodiversity Conservation', 2, 260],
            ['S70014', 'Kavitha a/p Raman', 'kavitha@ycc.test', '0191234504', 'FSPA', 'Aquaculture', 1, 240],
            ['S70015', 'Muhammad Irfan bin Zulkifli', 'irfan@ycc.test', '0131234505', 'FBESD', 'Economics', 3, 230],
            ['S70016', 'Nurul Izzah binti Hassan', 'izzah@ycc.test', '0141234506', 'FSSM', 'Marine Biology', 4, 220],
            ['S70017', 'Lim Jia Hui', 'jiahui@ycc.test', '0171234507', 'FSKM', 'Computer Science', 2, 150],
            ['S70018', 'Aiman Hakimi bin Yusof', 'aiman@ycc.test', '0181234508', 'FPM', 'Maritime Management', 3, 210],
            ['S70019', 'Farah Adlina binti Mohd Noor', 'farah@ycc.test', '0101234509', 'FSSM', 'Biodiversity Conservation', 1, 10],
            ['S70020', 'Daniel Lee Kah Meng', 'daniel@ycc.test', '0121234510', 'FSMA', 'Food Science', 1, 5],
        ];

        $pending = [
            ['S70031', 'Amirah Sofea binti Jamal', 'amirah@ycc.test', '0131112221', 'FSSM', 'Marine Biology', 1, 2],
            ['S70032', 'Haziq Iskandar bin Fauzi', 'haziq@ycc.test', '0191112222', 'FSPA', 'Fisheries', 1, 1],
            ['S70033', 'Chong Mei Xin', 'meixin@ycc.test', '0161112223', 'FSKM', 'Mathematics', 2, 0],
        ];

        $teams = array_keys(config('ycc.teams'));
        $n = 0;
        $make = function (array $row, string $role, string $state) use ($password, $teams, &$n): User {
            [$matric, $name, $email, $phone, $faculty, $programme, $year, $joined] = $row;
            $faculty = config('ycc.faculties.'.$faculty, $faculty);
            $team = $teams[$n++ % count($teams)];
            $user = User::query()->firstOrNew(['matric_no' => $matric]);
            $user->fill([
                'full_name' => $name, 'email' => $email, 'phone_number' => $phone, 'faculty' => $faculty, 'team' => $team, 'member_type' => 'student',
                'programme' => $programme, 'year_of_study' => $year, 'pdpa_consent_at' => now()->subDays($joined),
            ]);
            $user->password = $password;
            $user->forceFill([
                'role' => $role,
                'account_status' => $state,
                'reviewed_at' => $state === 'pending' ? null : now()->subDays($joined),
                'created_at' => now()->subDays($joined),
            ])->save();

            return $user;
        };

        $ex = array_map(fn ($r) => $make($r, 'admin', 'approved'), $excos);
        $m = array_map(fn ($r) => $make($r, 'member', 'approved'), $members);
        array_map(fn ($r) => $make($r, 'member', 'pending'), $pending);

        $creator = $ex[0];
        $program = function (string $title, string $category, string $venue, int $daysFromNow, int $hour, int $hours, ?int $capacity, string $desc, string $status = 'published', array $where = []) use ($creator): Program {
            $start = now()->startOfDay()->addDays($daysFromNow)->setTime($hour, 0);
            $p = Program::query()->firstOrNew(['title' => $title]);
            $p->fill([
                'description' => $desc, 'category' => $category, 'venue' => $venue,
                'starts_at' => $start, 'ends_at' => $start->copy()->addHours($hours),
                'registration_closes_at' => $start->copy()->subDay()->setTime(23, 59),
                'max_capacity' => $capacity, 'status' => $status, 'counts_for_activity' => true,
            ]);
            $p->fill($where + ['location_type' => 'in_person', 'latitude' => null, 'longitude' => null, 'meeting_url' => null, 'meeting_info' => null]);
            $p->created_by = $creator->id;
            $p->save();

            return $p;
        };

        // Past programs
        $p1 = $program('Teluk Ketapang Beach Cleanup', 'beach_cleanup', 'Pantai Teluk Ketapang, Kuala Nerus', -120, 7, 4, 40,
            "Our first big cleanup of the semester. We sorted and weighed debris by type so the data could go into the club's marine debris log.");
        $p2 = $program('Chagar Hutang Hatchery Volunteer Weekend', 'turtle_hatchery', 'Chagar Hutang Turtle Sanctuary, Pulau Redang', -75, 9, 30, 20,
            'Two days with the SEATRU team: night patrols, nest relocation and hatchery maintenance. Limited seats because of boat capacity.');
        $p2->forceFill(['volunteer_hours' => 16])->save();
        $p2b = $program('Turtle Hatchery Night Watch', 'turtle_hatchery', 'Setiu Turtle Hatchery, Terengganu', -50, 20, 4, 15,
            'Evening shift at the hatchery: checking nests, recording temperatures and guarding against predators.');
        $p3 = $program('Microplastics Sorting Workshop', 'workshop', 'Lab 2, Faculty of Science and Marine Environment, UMT', -20, 14, 3, 35,
            'Hands-on session sorting sand samples for microplastics, with a short talk on why they matter for marine life.');

        // Upcoming programs
        $p4 = $program('Mangrove Planting at Setiu Wetlands', 'restoration', 'Setiu Wetlands, Terengganu', 9, 8, 5, 30,
            'Plant mangrove seedlings with the local community. Bring a hat, water bottle and clothes that can get muddy. Transport from UMT provided.',
            'published', ['latitude' => 5.6573, 'longitude' => 102.7392]);
        $p5 = $program('Sea Turtles of Terengganu: Open Talk', 'talk', 'Dewan Kuliah Utama, UMT', 23, 20, 2, 120,
            'An evening talk on the state of sea turtle nesting in Terengganu, what the data shows, and how students can help. Open to all YCC members.',
            'published', ['location_type' => 'hybrid', 'latitude' => 5.4103, 'longitude' => 103.0868,
                'meeting_url' => 'https://umt.webex.com/meet/ycc', 'meeting_info' => 'Meeting number 2512 345 6789']);
        $program('Plastic-Free Campus: Online Sharing', 'talk', 'Online', 16, 21, 1, null,
            'A short online sharing on cutting single-use plastic at UMT, with ideas members can start this semester. Join from anywhere.',
            'published', ['location_type' => 'online', 'meeting_url' => 'https://umt.webex.com/meet/ycc', 'meeting_info' => 'Password: ycc2026']);
        $program('Coral Reef Snorkel Survey', 'coral_marine', 'Pulau Bidong', 40, 8, 8, 15,
            'Reef check style survey. Swimming test required. Details to be confirmed with the boat operator.', 'draft');

        $mark = function (Program $p, User $u, string $status) use ($creator): void {
            $reg = ProgramRegistration::query()->firstOrNew(['program_id' => $p->id, 'user_id' => $u->id]);
            $reg->fill([
                'status' => $status,
                'registered_at' => $p->starts_at->copy()->subDays(5),
                'checked_in_at' => $status === 'attended' ? $p->starts_at->copy()->addMinutes(rand(0, 40)) : null,
                'checked_in_by' => in_array($status, ['attended', 'absent'], true) ? $creator->id : null,
                'checkin_method' => $status === 'attended' ? (rand(0, 1) ? 'qr' : 'manual') : null,
            ])->save();
        };

        [$siti, $danial, $weiling, $kavitha, $irfan, $izzah, $jiahui, $aiman, $farah, $daniel] = $m;

        // Active: attended within 60 days
        foreach ([$siti, $weiling, $ex[0], $ex[1]] as $u) {
            $mark($p3, $u, 'attended');
        }
        // Danial last came ~50 days ago, so he shows up under "Slipping soon" in Analytics.
        foreach ([$danial, $siti] as $u) {
            $mark($p2b, $u, 'attended');
        }
        foreach ([$siti, $weiling, $ex[0]] as $u) {
            $mark($p2, $u, 'attended');
        }
        $mark($p1, $weiling, 'attended');

        // At-risk: last attended ~75 days ago; Kavitha also no-showed the workshop
        $mark($p2, $kavitha, 'attended');
        $mark($p2, $irfan, 'attended');
        $mark($p3, $kavitha, 'absent');

        // Inactive: last attended ~120 days ago, or never attended after the grace period
        $mark($p1, $izzah, 'attended');
        $mark($p1, $aiman, 'attended');
        $mark($p2, $jiahui, 'absent');

        // Upcoming registrations
        foreach ([$siti, $danial, $daniel, $kavitha, $ex[1]] as $u) {
            $mark($p4, $u, 'registered');
        }
        foreach ([$weiling, $farah, $irfan, $siti] as $u) {
            $mark($p5, $u, 'registered');
        }

        // A few ratings on the recent workshop, so excos can see how feedback looks.
        foreach ([[$siti, 5, 'Loved sorting the samples. More hands-on sessions like this please!'], [$weiling, 4, 'Great talk, but the room was a bit crowded.'], [$ex[1], 5, null]] as [$u, $rating, $comment]) {
            ProgramFeedback::query()->updateOrCreate(['program_id' => $p3->id, 'user_id' => $u->id], ['rating' => $rating, 'comment' => $comment]);
        }

        app(ActivityStatusService::class)->refreshAll();
    }
}
