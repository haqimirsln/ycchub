<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\ProgramRegistrationService;
use App\Services\RegistrationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Members land here after scanning the QR code an exco shows at the event. */
class CheckInController extends Controller
{
    public function show(Request $request, string $code): View
    {
        $program = Program::query()->where('checkin_code', $code)->firstOrFail();
        $registration = $program->registrations()->where('user_id', $request->user()->id)->first();

        return view('programs.checkin', [
            'program' => $program,
            'registration' => $registration,
        ]);
    }

    public function store(Request $request, string $code, ProgramRegistrationService $service): RedirectResponse
    {
        $program = Program::query()->where('checkin_code', $code)->firstOrFail();

        try {
            $service->selfCheckIn($program, $request->user());
        } catch (RegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('checkin.show', $code)->with('success', 'Checked in. Thanks for showing up!');
    }
}
