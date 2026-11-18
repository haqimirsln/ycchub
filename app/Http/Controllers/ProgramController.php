<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\ProgramRegistrationService;
use App\Services\RegistrationException;
use App\Support\DatabaseStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'past' ? 'past' : 'upcoming';
        $category = $request->query('category');

        $query = Program::query()->published()->withSeatCounts()
            ->when($category && array_key_exists($category, config('ycc.program_categories')), fn ($q) => $q->where('category', $category));

        $programs = $tab === 'past'
            ? $query->past()->paginate(12)->withQueryString()
            : $query->upcoming()->paginate(12)->withQueryString();

        $myRegs = $request->user()->registrations()->get()->keyBy('program_id');

        return view('programs.index', [
            'programs' => $programs,
            'tab' => $tab,
            'category' => $category,
            'myRegs' => $myRegs,
            'categories' => config('ycc.program_categories'),
        ]);
    }

    public function show(Request $request, Program $program): View
    {
        $user = $request->user();
        abort_if($program->status === 'draft' && ! $user->isExco(), 404);

        $program->loadCount([
            'registrations as seats_taken' => fn ($r) => $r->whereIn('status', ['registered', 'attended', 'absent']),
            'registrations as attended_count' => fn ($r) => $r->where('status', 'attended'),
        ]);

        $feedbackReady = DatabaseStatus::feedbackReady();
        $feedback = $feedbackReady && $user->isExco()
            ? $program->feedback()->latest()->get(['rating', 'comment', 'created_at'])
            : collect();

        return view('programs.show', [
            'program' => $program,
            'registration' => $program->registrations()->where('user_id', $user->id)->first(),
            'feedbackReady' => $feedbackReady,
            'myFeedback' => $feedbackReady ? $program->feedback()->where('user_id', $user->id)->first() : null,
            'feedback' => $feedback,
            'committee' => DatabaseStatus::hasTable('program_committee')
                ? $program->committee()->with('user:id,full_name,matric_no,account_status')->get()
                : collect(),
        ]);
    }

    /** An .ics file for Apple Calendar, Outlook and anything else that isn't Google. */
    public function calendar(Request $request, Program $program): Response
    {
        abort_if($program->status === 'draft' && ! $request->user()->isExco(), 404);

        $esc = fn (string $v) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $v);
        $utc = fn ($t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $host = parse_url(url('/'), PHP_URL_HOST) ?: 'ycc-hub';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Young Conservationist Club//YCC Hub//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:program-'.$program->id.'@'.$host,
            'DTSTAMP:'.$utc(now()),
            'DTSTART:'.$utc($program->starts_at),
            'DTEND:'.$utc($program->calendarEndsAt()),
            'SUMMARY:'.$esc($program->title.' (YCC)'),
            'LOCATION:'.$esc($program->calendarLocation()),
            'DESCRIPTION:'.$esc($program->calendarDetails()),
            'URL:'.route('programs.show', $program),
            'STATUS:'.($program->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$esc($program->title),
            'TRIGGER:-PT2H',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        // Calendar files wrap long lines at 75 bytes, continuing with a space.
        $folded = array_map(function (string $line) {
            $out = '';
            while (strlen($line) > 75) {
                $cut = 75;
                while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                    $cut--; // don't split a multi-byte character
                }
                $out .= substr($line, 0, $cut)."\r\n ";
                $line = substr($line, $cut);
            }

            return $out.$line;
        }, $lines);

        $name = Str::slug($program->title) ?: 'ycc-program';

        return response(implode("\r\n", $folded)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'.ics"',
        ]);
    }

    public function register(Request $request, Program $program, ProgramRegistrationService $service): RedirectResponse|JsonResponse
    {
        try {
            $service->register($program, $request->user());
        } catch (RegistrationException $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond($request, true, "You're in! See you at {$program->title}.");
    }

    public function cancel(Request $request, Program $program, ProgramRegistrationService $service): RedirectResponse|JsonResponse
    {
        try {
            $service->cancel($program, $request->user());
        } catch (RegistrationException $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond($request, true, 'Your registration has been cancelled.');
    }

    private function respond(Request $request, bool $ok, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
