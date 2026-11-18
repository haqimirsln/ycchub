<?php

namespace Tests\Unit;

use App\Models\ActivityPause;
use App\Services\ActivityStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityStatusTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): ActivityStatusService
    {
        return new ActivityStatusService;
    }

    public function test_boundaries(): void
    {
        $today = CarbonImmutable::parse('2026-10-20');
        $joined = $today->subYear();

        $this->assertSame('active', $this->svc()->classify($today->subDays(60), $joined, 1, $today));
        $this->assertSame('at_risk', $this->svc()->classify($today->subDays(61), $joined, 1, $today));
        $this->assertSame('at_risk', $this->svc()->classify($today->subDays(90), $joined, 1, $today));
        $this->assertSame('inactive', $this->svc()->classify($today->subDays(91), $joined, 1, $today));
    }

    public function test_new_members_get_a_grace_period(): void
    {
        $today = CarbonImmutable::parse('2026-10-20');

        $this->assertSame('new', $this->svc()->classify(null, $today->subDays(10), 0, $today));
        $this->assertSame('new', $this->svc()->classify(null, $today->subDays(60), 0, $today));
        $this->assertSame('inactive', $this->svc()->classify(null, $today->subDays(61), 0, $today));
    }

    public function test_pause_days_are_not_counted(): void
    {
        $today = CarbonImmutable::parse('2026-10-20');
        $last = $today->subDays(100);

        $this->assertSame('inactive', $this->svc()->classify($last, $today->subYear(), 1, $today));

        // A 50-day semester break inside the gap brings it down to 50 counted days.
        ActivityPause::create(['label' => 'Break', 'starts_on' => $today->subDays(80), 'ends_on' => $today->subDays(31)]);
        $this->assertSame(50, $this->svc()->countedDaysBetween($last, $today));
        $this->assertSame('active', $this->svc()->classify($last, $today->subYear(), 1, $today));
    }

    public function test_overlapping_pauses_are_not_double_counted(): void
    {
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2026-03-02'); // 60 days

        ActivityPause::create(['label' => 'A', 'starts_on' => '2026-01-11', 'ends_on' => '2026-01-20']);
        ActivityPause::create(['label' => 'B', 'starts_on' => '2026-01-16', 'ends_on' => '2026-01-25']);

        $this->assertSame(45, $this->svc()->countedDaysBetween($from, $to));
    }
}
