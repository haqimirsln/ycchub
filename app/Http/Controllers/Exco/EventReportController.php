<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Services\MtService;
use App\Support\DatabaseStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * One-page event report for the MT: details, committee, attendance numbers,
 * who came (and who was new), feedback and an attendee list. Laid out for A4,
 * so "Print" or "Save as PDF" in the browser gives a report ready to submit.
 */
class EventReportController extends Controller
{
    public function show(Request $request, Program $program, MtService $mt): View
    {
        $registrations = $program->registrations()
            ->with('user:id,full_name,matric_no,faculty,team,member_type')
            ->get()
            ->filter(fn (ProgramRegistration $r) => $r->user !== null);

        $byStatus = $registrations->groupBy('status');
        $attended = ($byStatus->get('attended') ?? collect())
            ->sortBy(fn ($r) => mb_strtolower($r->user->full_name))
            ->values();

        $onList = $registrations->whereIn('status', ['registered', 'attended', 'absent'])->count();
        $hours = $program->volunteerHours();

        return view('exco.report', [
            'program' => $program,
            'committee' => $program->committee()->with('user:id,full_name,matric_no')->get(),
            'attended' => $attended,
            'firstTimers' => $this->firstTimers($program, $attended),
            'counts' => [
                'on_list' => $onList,
                'attended' => $attended->count(),
                'absent' => ($byStatus->get('absent') ?? collect())->count(),
                'unmarked' => ($byStatus->get('registered') ?? collect())->count(),
                'cancelled' => ($byStatus->get('cancelled') ?? collect())->count(),
                'qr' => $attended->where('checkin_method', 'qr')->count(),
                'manual' => $attended->where('checkin_method', '!=', 'qr')->count(),
            ],
            'rate' => $onList ? (int) round($attended->count() / $onList * 100) : null,
            'hoursEach' => $hours,
            'hoursTotal' => round($hours * $attended->count(), 1),
            'byTeam' => $this->tally($attended, fn ($u) => $u->teamName() ?? 'No team yet'),
            'byFaculty' => $this->tally($attended, fn ($u) => $u->facultyName() ?? 'Not set'),
            'feedback' => DatabaseStatus::feedbackReady() ? $program->feedback()->latest()->get() : null,
            'preparedBy' => $request->user(),
            'advisor' => $mt->advisor(),
            'session' => $mt->session(),
        ]);
    }

    /**
     * Attendees for whom this was their first YCC event, the people the club
     * most wants to see come back.
     *
     * @param  Collection<int, ProgramRegistration>  $attended
     * @return Collection<int, int> user ids
     */
    private function firstTimers(Program $program, Collection $attended): Collection
    {
        $ids = $attended->pluck('user_id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $cameBefore = ProgramRegistration::query()
            ->whereIn('user_id', $ids)
            ->where('status', 'attended')
            ->where('program_id', '!=', $program->id)
            ->whereHas('program', fn ($q) => $q->where('starts_at', '<', $program->starts_at))
            ->distinct()
            ->pluck('user_id');

        return $ids->diff($cameBefore)->values();
    }

    /** @return array<string, int> label => count, biggest first */
    private function tally(Collection $attended, callable $label): array
    {
        return $attended->countBy(fn ($r) => $label($r->user))->sortDesc()->all();
    }
}
