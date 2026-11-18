<?php

namespace App\Http\Controllers\Exco;

use App\Support\Activity;
use App\Http\Controllers\Controller;
use App\Models\ExportLog;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\ProgramRegistrationService;
use App\Services\RegistrationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(private ProgramRegistrationService $service) {}

    public function show(Request $request, Program $program): View|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['counts' => $this->counts($program), 'checkin_open' => $program->checkin_open]);
        }

        $registrations = $program->registrations()
            ->with('user:id,full_name,matric_no,phone_number,faculty,activity_status')
            ->whereIn('status', ['registered', 'attended', 'absent'])
            ->get()
            ->sortBy(fn ($r) => mb_strtolower($r->user->full_name))
            ->values();

        $cancelled = $program->registrations()->with('user:id,full_name,matric_no')->where('status', 'cancelled')->get();

        return view('exco.attendance', [
            'program' => $program,
            'rows' => $registrations->map(fn (ProgramRegistration $r) => [
                'user_id' => $r->user_id,
                'name' => $r->user->full_name,
                'matric' => $r->user->matric_no,
                'faculty' => $r->user->faculty,
                'status' => $r->status,
                'method' => $r->checkin_method,
                'checked_in_at' => $r->checked_in_at?->format('g:i A'),
            ])->values(),
            'cancelled' => $cancelled,
            'counts' => $this->counts($program),
            'editable' => ! $request->user()->isAdvisor() && ($program->attendanceEditable() || $request->user()->isSuperadmin()),
        ]);
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(['attended', 'absent', 'registered'])],
        ]);

        if (! $program->attendanceEditable() && ! $request->user()->isSuperadmin()) {
            return response()->json(['message' => 'Attendance for this event is locked.'], 422);
        }

        $member = User::query()->findOrFail($data['user_id']);
        if (! $member->isApproved()) {
            return response()->json(['message' => 'That account is not an approved member.'], 422);
        }

        try {
            $reg = $this->service->markAttendance($program, $member, $data['status'], $request->user());
            if (! $program->attendanceEditable()) {
                Activity::log('event.attendance', "Changed locked attendance: {$member->full_name} set to {$data['status']} at {$program->title}", $program);
            }
        } catch (RegistrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'row' => [
                'user_id' => $member->id,
                'name' => $member->full_name,
                'matric' => $member->matric_no,
                'faculty' => $member->faculty,
                'status' => $reg->status,
                'method' => $reg->checkin_method,
                'checked_in_at' => $reg->checked_in_at?->format('g:i A'),
            ],
            'counts' => $this->counts($program),
        ]);
    }

    /** Find approved members who aren't on the list yet (walk-ins). */
    public function searchMembers(Request $request, Program $program): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $onList = $program->registrations()->whereIn('status', ['registered', 'attended', 'absent'])->pluck('user_id');
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $results = User::query()->approved()
            ->whereNotIn('id', $onList)
            ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('matric_no', 'like', $like))
            ->orderBy('full_name')
            ->limit(8)
            ->get(['id', 'full_name', 'matric_no', 'faculty']);

        return response()->json(['results' => $results->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->full_name, 'matric' => $u->matric_no, 'faculty' => $u->faculty,
        ])]);
    }

    public function markRemainingAbsent(Request $request, Program $program): RedirectResponse
    {
        if (! $program->hasStarted()) {
            return back()->with('error', 'You can only mark absentees after the event has started.');
        }
        if (! $program->attendanceEditable() && ! $request->user()->isSuperadmin()) {
            return back()->with('error', 'Attendance for this event is locked.');
        }

        $count = $this->service->markRemainingAbsent($program, $request->user());
        if ($count) {
            Activity::log('event.attendance', "Marked {$count} unmarked member(s) absent at {$program->title}", $program);
        }

        return back()->with('success', $count ? "{$count} unmarked member(s) set to absent." : 'Everyone is already marked.');
    }

    public function toggleCheckin(Request $request, Program $program): RedirectResponse|JsonResponse
    {
        if ($program->status !== 'published') {
            return back()->with('error', 'Only published events can open check-in.');
        }

        $program->checkin_open = ! $program->checkin_open;
        $program->save();

        $msg = $program->checkin_open ? 'QR check-in is open.' : 'QR check-in is closed.';
        Activity::log('event.attendance', ($program->checkin_open ? 'Opened' : 'Closed')." QR check-in for {$program->title}", $program);

        return $request->expectsJson()
            ? response()->json(['checkin_open' => $program->checkin_open, 'message' => $msg])
            : back()->with('success', $msg);
    }

    /** Full-screen QR for projecting or showing on a phone at the event. */
    public function qr(Program $program): View
    {
        return view('exco.qr', [
            'program' => $program,
            'checkinUrl' => route('checkin.show', $program->checkin_code),
            'counts' => $this->counts($program),
        ]);
    }

    public function export(Request $request, Program $program): StreamedResponse
    {
        $rows = $program->registrations()->with('user')->whereIn('status', ['registered', 'attended', 'absent'])->get()
            ->sortBy(fn ($r) => $r->user->full_name)->values();

        ExportLog::create([
            'user_id' => $request->user()->id,
            'export_type' => 'attendance',
            'filters' => 'program_id='.$program->id,
            'row_count' => $rows->count(),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $filename = 'attendance-'.str($program->title)->slug().'-'.$program->starts_at->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Matric No', 'Full Name', 'Faculty', 'Phone', 'Status', 'Check-in Time', 'Method']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->user->matric_no,
                    $r->user->full_name,
                    $r->user->facultyName(),
                    $r->user->whatsappNumber() ? '+'.$r->user->whatsappNumber() : $r->user->phone_number,
                    $r->statusLabel(),
                    $r->checked_in_at?->format('Y-m-d H:i'),
                    $r->checkin_method ? strtoupper($r->checkin_method) : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function counts(Program $program): array
    {
        $byStatus = $program->registrations()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $registered = (int) ($byStatus['registered'] ?? 0);
        $attended = (int) ($byStatus['attended'] ?? 0);
        $absent = (int) ($byStatus['absent'] ?? 0);

        return [
            'total' => $registered + $attended + $absent,
            'attended' => $attended,
            'absent' => $absent,
            'unmarked' => $registered,
            'capacity' => $program->max_capacity,
        ];
    }
}
