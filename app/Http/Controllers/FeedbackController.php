<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramFeedback;
use App\Support\DatabaseStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /** Members who attended rate the event (1 to 5) with an optional comment. They can change it later. */
    public function store(Request $request, Program $program): RedirectResponse
    {
        abort_unless(DatabaseStatus::feedbackReady(), 404);

        $user = $request->user();
        $attended = $program->registrations()->where('user_id', $user->id)->where('status', 'attended')->exists();
        abort_unless($attended, 403, 'Only members who attended can rate this event.');

        if (! $program->isFeedbackOpen()) {
            return back()->with('error', 'Feedback for this event has closed.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['rating.required' => 'Pick from 1 to 5 stars.']);

        ProgramFeedback::query()->updateOrCreate(
            ['program_id' => $program->id, 'user_id' => $user->id],
            ['rating' => $data['rating'], 'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null],
        );

        return back()->with('success', 'Thanks for the feedback!');
    }
}
