<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\ExportLog;
use App\Models\User;
use App\Services\ActivityStatusService;
use App\Services\MeritService;
use App\Services\VolunteerHoursService;
use App\Models\MeritEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    private const STATUS_FILTERS = ['all', 'attention', 'active', 'at_risk', 'inactive', 'new'];

    public function index(Request $request, MeritService $merit): View
    {
        $filters = $this->filters($request);

        $members = $this->query($filters)
            ->withCount(['registrations as no_show_count' => fn ($q) => $q->where('status', 'absent')])
            ->paginate(25)
            ->withQueryString();

        $faculties = config('ycc.faculties');

        return view('exco.members.index', [
            'members' => $members,
            'meritTotals' => $merit->totals($members->pluck('id')->all()),
            'filters' => $filters,
            'faculties' => $faculties,
        ]);
    }

    public function show(User $user, ActivityStatusService $activity, VolunteerHoursService $volunteer, MeritService $merit): View
    {
        abort_unless($user->isApproved() || $user->account_status === 'suspended', 404);

        $user->load('reviewer:id,full_name');
        $history = $user->registrations()->with('program')->get()
            ->sortByDesc(fn ($r) => $r->program?->starts_at)
            ->values();

        // Only events that already happened count towards the attendance rate.
        $attended = $history->where('status', 'attended')->count();
        $absent = $history->where('status', 'absent')->count();
        $upcoming = $history->filter(fn ($r) => $r->status === 'registered' && $r->program && $r->program->starts_at->isFuture())->count();

        $rules = config('ycc.activity');
        $joinedOn = $user->reviewed_at ?? $user->created_at;
        $daysAway = $activity->countedDaysBetween($user->last_attended_on ?? $joinedOn, now()->startOfDay());

        $statusNote = match ($user->activity_status) {
            'new' => $user->total_attended > 0
                ? 'New member, already joined an event.'
                : 'New member, still in the '.$rules['grace_days'].'-day welcome period.',
            'active' => 'Turns at-risk in '.max(0, $rules['active_days'] - $daysAway + 1).' days without an event.',
            'at_risk' => 'Turns inactive in '.max(0, $rules['at_risk_days'] - $daysAway + 1).' days without an event.',
            default => 'Away for '.$daysAway.' counted days. One event brings them back to active.',
        };

        return view('exco.members.show', [
            'member' => $user,
            'history' => $history,
            'stats' => [
                'attended' => $attended,
                'absent' => $absent,
                'upcoming' => $upcoming,
                'rate' => ($attended + $absent) > 0 ? (int) round($attended / ($attended + $absent) * 100) : null,
                'days_away' => $daysAway,
                'hours' => $volunteer->forUser($user),
            ],
            'statusNote' => $statusNote,
            'merit' => $merit->summary($user),
            'mtHistory' => app(\App\Services\MtService::class)->history($user),
            'meritEntries' => $merit->ready() ? MeritEntry::query()->where('user_id', $user->id)->with('giver:id,full_name')->latest('created_at')->latest('id')->limit(30)->get() : collect(),
        ]);
    }

    /** CSV of the current filter. Contains personal data, so every export is logged. */
    public function export(Request $request, VolunteerHoursService $volunteer): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->query($filters)
            ->withCount(['registrations as no_show_count' => fn ($q) => $q->where('status', 'absent')])
            ->get();

        ExportLog::create([
            'user_id' => $request->user()->id,
            'export_type' => 'members',
            'filters' => mb_substr(http_build_query(array_filter($filters)), 0, 255),
            'row_count' => $rows->count(),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $filename = 'ycc-members-'.$filters['status'].'-'.now()->format('Ymd-His').'.csv';
        $hours = $volunteer->byUser(now()->year, $rows->pluck('id')->all());

        return response()->streamDownload(function () use ($rows, $hours) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens names correctly
            fputcsv($out, ['Matric No', 'Full Name', 'Email', 'Phone', 'Faculty', 'Programme', 'Year', 'Role', 'Status', 'Last Attended', 'Total Attended', 'No-shows', 'Volunteer Hours '.now()->year, 'Joined']);
            foreach ($rows as $m) {
                fputcsv($out, [
                    $m->matric_no,
                    $m->full_name,
                    $m->email,
                    $m->whatsappNumber() ? '+'.$m->whatsappNumber() : $m->phone_number, // international format survives Excel
                    $m->facultyName(),
                    $m->programme,
                    $m->year_of_study,
                    ucfirst($m->role),
                    $m->activityLabel(),
                    $m->last_attended_on?->format('Y-m-d') ?? 'Never',
                    $m->total_attended,
                    $m->no_show_count,
                    $hours->get($m->id, 0),
                    ($m->reviewed_at ?? $m->created_at)?->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $status = $request->query('status', 'attention');

        return [
            'status' => in_array($status, self::STATUS_FILTERS, true) ? $status : 'attention',
            'q' => trim((string) $request->query('q', '')),
            'faculty' => (string) $request->query('faculty', ''),
            'team' => array_key_exists((string) $request->query('team'), config('ycc.teams')) || $request->query('team') === 'none' ? (string) $request->query('team') : '',
            'type' => in_array($request->query('type'), ['student', 'alumni'], true) ? (string) $request->query('type') : '',
            'role' => in_array($request->query('role'), ['member', 'admin'], true) ? $request->query('role') : '',
            'year' => in_array($request->query('year'), ['1', '2', '3', '4', '5', 'none'], true) ? (string) $request->query('year') : '',
        ];
    }

    private function query(array $f): Builder
    {
        return User::query()->tracked()
            ->when($f['status'] === 'attention', fn ($q) => $q->whereIn('activity_status', ['at_risk', 'inactive']))
            ->when(! in_array($f['status'], ['all', 'attention'], true), fn ($q) => $q->where('activity_status', $f['status']))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['q']).'%';
                $q->where(fn ($w) => $w->where('full_name', 'like', $term)
                    ->orWhere('matric_no', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone_number', 'like', $term));
            })
            ->when($f['faculty'] === 'none', fn ($q) => $q->whereNull('faculty'))
            ->when($f['faculty'] !== '' && $f['faculty'] !== 'none', fn ($q) => $q->where('faculty', $f['faculty']))
            ->when($f['year'] === 'none', fn ($q) => $q->whereNull('year_of_study'))
            ->when($f['year'] !== '' && $f['year'] !== 'none', fn ($q) => $q->where('year_of_study', (int) $f['year']))
            ->when($f['role'] !== '', fn ($q) => $q->where('role', $f['role']))
            ->when($f['team'] === 'none', fn ($q) => $q->whereNull('team'))
            ->when($f['team'] !== '' && $f['team'] !== 'none', fn ($q) => $q->where('team', $f['team']))
            ->when($f['type'] !== '', fn ($q) => $q->where('member_type', $f['type']))
            // Longest-gone first; members who never attended come first.
            ->orderByRaw('CASE WHEN last_attended_on IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_attended_on')
            ->orderBy('full_name');
    }
}
