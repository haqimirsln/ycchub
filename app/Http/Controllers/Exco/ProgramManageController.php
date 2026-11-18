<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramCommittee;
use App\Models\User;
use App\Services\MeritService;
use App\Services\AnnouncementService;
use App\Services\PushService;
use App\Models\Announcement;
use App\Support\Activity;
use App\Services\ActivityStatusService;
use App\Support\DatabaseStatus;
use App\Support\MapLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgramManageController extends Controller
{
    public function __construct(private ActivityStatusService $activity) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['upcoming', 'past', 'drafts', 'cancelled'], true) ? $request->query('tab') : 'upcoming';

        $query = Program::query()->withSeatCounts()->with('creator');
        if (DatabaseStatus::feedbackReady()) {
            $query->withAvg('feedback', 'rating')->withCount('feedback');
        }
        $query = match ($tab) {
            'past' => $query->where('status', 'published')->past(),
            'drafts' => $query->where('status', 'draft')->orderBy('starts_at'),
            'cancelled' => $query->where('status', 'cancelled')->orderByDesc('starts_at'),
            default => $query->where('status', 'published')->upcoming(),
        };

        return view('exco.programs.index', [
            'programs' => $query->paginate(15)->withQueryString(),
            'tab' => $tab,
            'draftCount' => Program::query()->where('status', 'draft')->count(),
        ]);
    }

    public function create(): View
    {
        return view('exco.programs.form', [
            'program' => new Program([
                'status' => 'published',
                'counts_for_activity' => true,
                'category' => 'beach_cleanup',
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $program = new Program($data);
        $program->created_by = $request->user()->id;
        $program->poster_path = $this->storePoster($request);
        $program->save();
        $program->makePosterThumb();
        $this->saveCommittee($request, $program);
        $emailed = $this->announce($request, $program).$this->notifyPhones($request, $program);
        Activity::log('event.created', ($program->status === 'draft' ? 'Saved a draft event: ' : 'Created event: ').$program->title, $program);

        return redirect()->route('programs.show', $program)->with('success', ($program->status === 'draft'
            ? 'Draft saved. Members can\'t see it until you publish.'
            : 'Event published. Members can register now.').$emailed);
    }

    public function edit(Program $program): View
    {
        return view('exco.programs.form', [
            'program' => $program,
            ...$this->formData($program),
        ]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $data = $this->validated($request, $program);
        $countsChanged = (bool) $program->counts_for_activity !== (bool) $data['counts_for_activity'];

        $program->fill($data);

        if ($request->boolean('remove_poster') && $program->poster_path) {
            $program->deletePosterFiles();
            $program->poster_path = null;
        }
        if ($request->hasFile('poster')) {
            $program->deletePosterFiles();
            $program->poster_path = $this->storePoster($request);
        }
        $program->save();
        if ($request->hasFile('poster')) {
            $program->makePosterThumb();
        }

        if ($countsChanged || $program->wasChanged('starts_at')) {
            $this->activity->refreshAll($program->registrations()->pluck('user_id')->all());
        }
        $this->saveCommittee($request, $program);
        if ($program->wasChanged('starts_at') && DatabaseStatus::hasColumn('program_registrations', 'reminded_at')) {
            // New time, so registered members should get a fresh reminder.
            $program->registrations()->update(['reminded_at' => null]);
        }
        $emailed = $this->announce($request, $program).$this->notifyPhones($request, $program);
        Activity::log('event.updated', 'Edited event: '.$program->title, $program);

        return redirect()->route('programs.show', $program)->with('success', 'Event updated.'.$emailed);
    }

    /** Cancelling keeps the record (and tells registered members) instead of deleting history. */
    public function cancel(Program $program): RedirectResponse
    {
        if ($program->status === 'cancelled') {
            return back()->with('error', 'This event is already cancelled.');
        }

        $program->update(['status' => 'cancelled']);
        $program->checkin_open = false;
        $program->save();
        $this->activity->refreshAll($program->registrations()->pluck('user_id')->all());
        $this->syncCommitteeMerit($program);
        Activity::log('event.cancelled', 'Cancelled event: '.$program->title, $program);

        return redirect()->route('exco.programs.index')->with('success', "{$program->title} has been cancelled.");
    }

    /** Superadmin only. Soft delete: attendance stops counting toward member status. */
    public function destroy(Program $program): RedirectResponse
    {
        $userIds = $program->registrations()->pluck('user_id')->all();
        $committeeIds = $this->committeeUserIds($program);
        $program->delete();
        $this->activity->refreshAll($userIds);
        $this->syncMeritFor($committeeIds);
        Activity::log('event.deleted', 'Deleted event: '.$program->title, $program);

        return redirect()->route('exco.programs.index')->with('success', "{$program->title} deleted.");
    }

    private function validated(Request $request, ?Program $program = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(config('ycc.program_categories')))],
            'location_type' => ['nullable', Rule::in(array_keys(Program::LOCATION_TYPES))],
            'venue' => ['nullable', 'required_unless:location_type,online', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'meeting_url' => ['nullable', 'url:https', 'max:500'],
            'meeting_info' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'registration_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'volunteer_hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'counts_for_activity' => ['nullable', 'boolean'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'committee_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'announce' => ['nullable', 'boolean'],
            'announce_team' => ['nullable', Rule::in(array_keys(config('ycc.teams')))],
            'announce_note' => ['nullable', 'string', 'max:1000'],
            'notify_push' => ['nullable', 'boolean'],
            'committee' => ['nullable', 'array', 'max:100'],
            'committee.*.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'committee.*.position' => ['nullable', 'string', 'max:100'],
        ], [
            'committee.*.user_id.exists' => 'One of the AJK members no longer exists. Pick again.',
            'committee.*.position.max' => 'Keep each AJK position under 100 characters.',
            'ends_at.after' => 'End time must be after the start time.',
            'registration_closes_at.before_or_equal' => 'Registration must close before the event starts.',
            'poster.max' => 'Poster must be 3 MB or smaller.',
            'venue.required_unless' => 'Type where it happens, e.g. Setiu Wetlands, Terengganu.',
            'meeting_url.url' => 'Paste the full meeting link, starting with https://',
        ]);

        // Where it happens. Until the database update has been applied, events stay in-person only.
        if (DatabaseStatus::hasColumn('programs', 'location_type')) {
            $type = $data['location_type'] ?? 'in_person';
            $data['location_type'] = $type;
            if ($type === 'online') {
                $data['venue'] = 'Online';
                $data['latitude'] = $data['longitude'] = null;
            } else {
                $data['latitude'] = isset($data['latitude']) ? round((float) $data['latitude'], 7) : null;
                $data['longitude'] = isset($data['longitude']) ? round((float) $data['longitude'], 7) : null;
            }
            if ($type === 'in_person') {
                $data['meeting_url'] = $data['meeting_info'] = null;
            } else {
                $data['meeting_url'] = $data['meeting_url'] ?? null;
                $data['meeting_info'] = $data['meeting_info'] ?? null;
            }
        } else {
            if (($data['location_type'] ?? 'in_person') !== 'in_person') {
                back()->withInput()->withErrors(['location_type' => 'Online events need a quick database update first. An admin can press "Update database" in Settings.'])->throwResponse();
            }
            unset($data['location_type'], $data['latitude'], $data['longitude'], $data['meeting_url'], $data['meeting_info']);
        }

        // Editing a cancelled event should not silently revive it.
        if ($program && $program->status === 'cancelled') {
            $data['status'] = 'cancelled';
        }

        $data['max_capacity'] = $data['max_capacity'] ?? null;
        $data['ends_at'] = $data['ends_at'] ?? null;
        $data['registration_closes_at'] = $data['registration_closes_at'] ?? null;

        if ($program && $data['max_capacity'] !== null) {
            $taken = $program->seatsTaken();
            if ($data['max_capacity'] < $taken) {
                back()->withInput()->withErrors(['max_capacity' => "Capacity can't be lower than the {$taken} seats already taken."])->throwResponse();
            }
        }

        $data['counts_for_activity'] = $request->boolean('counts_for_activity');
        if (DatabaseStatus::hasColumn('programs', 'volunteer_hours')) {
            $data['volunteer_hours'] = isset($data['volunteer_hours']) && $data['volunteer_hours'] !== '' ? round((float) $data['volunteer_hours'], 1) : null;
        } else {
            unset($data['volunteer_hours']);
        }
        unset($data['poster'], $data['committee'], $data['announce'], $data['announce_team'], $data['announce_note'], $data['notify_push']);
        if (DatabaseStatus::hasColumn('programs', 'committee_count')) {
            $data['committee_count'] = $data['committee_count'] ?? null;
        } else {
            unset($data['committee_count']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function formData(?Program $program = null): array
    {
        $committeeReady = DatabaseStatus::hasTable('program_committee');

        return [
            'categories' => config('ycc.program_categories'),
            'locationReady' => DatabaseStatus::hasColumn('programs', 'location_type'),
            'hoursReady' => DatabaseStatus::hasColumn('programs', 'volunteer_hours'),
            'committeeReady' => $committeeReady,
            'announceReady' => app(AnnouncementService::class)->ready() && DatabaseStatus::hasColumn('programs', 'announced_at'),
            'announcement' => $program?->announcement_id ? Announcement::query()->find($program->announcement_id) : null,
            'pushReady' => app(PushService::class)->ready() && DatabaseStatus::hasColumn('programs', 'pushed_at'),
            'pushMembers' => app(PushService::class)->stats()['members'],
            'committeeRows' => $committeeReady && $program?->exists
                ? $program->committee()->get(['user_id', 'position'])->map(fn ($c) => ['user_id' => $c->user_id ? (string) $c->user_id : '', 'position' => $c->position])->all()
                : [],
            'memberOptions' => $committeeReady
                ? User::query()->approved()->orderBy('full_name')->get(['id', 'full_name', 'matric_no'])
                    ->map(fn ($u) => ['id' => (string) $u->id, 'label' => "{$u->full_name} ({$u->matric_no})"])->all()
                : [],
        ];
    }

    /**
     * "Email members about this event" on the event form. Uses the same batched
     * sending as the Email members page, so it shows up there too.
     *
     * @return string extra text for the success message
     */
    /** "New event" phone notification, sent once per event when it's published. */
    private function notifyPhones(Request $request, Program $program): string
    {
        $push = app(PushService::class);
        if (! $request->boolean('notify_push') || $program->status !== 'published' || ! $push->ready()
            || ! DatabaseStatus::hasColumn('programs', 'pushed_at') || $program->pushed_at) {
            return '';
        }
        // Say why nothing went out, so a missing notification is never a mystery.
        if ($program->starts_at->isPast()) {
            return ' No phone notification: the event has already started.';
        }

        $count = $push->queueNewEvent($program, $request->user());
        if (! $count) {
            return ' No phone notification: no other members have notifications turned on yet.';
        }
        if (! app()->runningUnitTests()) {
            // Start sending as soon as the page has gone back to the MT, not on the next cron tick.
            app()->terminating(fn () => $push->sendDue());
        }

        return " Phone notification going to {$count} ".($count === 1 ? 'member' : 'members').'.';
    }

    private function announce(Request $request, Program $program): string
    {
        $emails = app(AnnouncementService::class);
        if (! $request->boolean('announce') || $program->status !== 'published' || ! $emails->ready()
            || ! DatabaseStatus::hasColumn('programs', 'announced_at')) {
            return '';
        }

        $team = $request->input('announce_team') ?: null;
        $note = trim((string) $request->input('announce_note', ''));
        $body = '<p>Hi {name},</p><p>Registration is open for <strong>'.e($program->title).'</strong>'
            .($team ? ', and we\'d especially love to see the '.e(config("ycc.teams.$team")).' there' : '').'.</p>';
        foreach (preg_split('/\R{2,}/', $note) as $para) {
            if (trim($para) !== '') {
                $body .= '<p>'.nl2br(e(trim($para)), false).'</p>';
            }
        }
        if ($program->posterUrl()) {
            $body .= '<p><img src="'.e($program->posterUrl()).'" alt="'.e('Poster for '.$program->title).'"></p>';
        }
        $body .= '<p>'.($program->max_capacity ? 'Seats are limited, so register early. ' : '').'See you there!</p>';

        $announcement = new Announcement([
            'subject' => 'New event: '.$program->title,
            'body' => $body,
            'program_id' => $program->id,
            'audience' => $emails->audience($team ? ['teams' => [$team]] : []),
        ]);
        $announcement->created_by = $request->user()->id;
        $emails->start($announcement);

        $program->forceFill(['announced_at' => now(), 'announcement_id' => $announcement->id])->save();
        Activity::log('system.email', "Announced \"{$program->title}\" to {$announcement->recipient_count} members ({$announcement->audience_label})", $program);

        if ($announcement->recipient_count === 0) {
            return ' Nobody matched, so no email was sent.';
        }

        return $announcement->remaining()
            ? " Emailing {$announcement->recipient_count} members: {$announcement->sent_count} sent now, the rest over the next few minutes."
            : " Emailed {$announcement->sent_count} ".($announcement->sent_count === 1 ? 'member' : 'members').'.';
    }

    /** Replaces the event's AJK list and updates merit for anyone added or removed. */
    private function saveCommittee(Request $request, Program $program): void
    {
        if (! DatabaseStatus::hasTable('program_committee') || ! $request->has('committee_present')) {
            return;
        }

        $rows = collect($request->input('committee', []))
            ->map(fn ($r) => [
                'user_id' => filled($r['user_id'] ?? null) ? (int) $r['user_id'] : null,
                'position' => trim((string) ($r['position'] ?? '')),
            ])
            ->filter(fn ($r) => $r['user_id'] || $r['position'] !== '')
            ->map(fn ($r) => [...$r, 'position' => $r['position'] !== '' ? mb_substr($r['position'], 0, 100) : 'AJK'])
            ->values();

        $before = $this->committeeUserIds($program);
        $program->committee()->delete();
        foreach ($rows as $i => $r) {
            ProgramCommittee::query()->create([...$r, 'program_id' => $program->id, 'sort' => $i]);
        }

        $this->syncMeritFor(array_unique([...$before, ...$rows->pluck('user_id')->filter()->all()]));
    }

    private function syncCommitteeMerit(Program $program): void
    {
        $this->syncMeritFor($this->committeeUserIds($program));
    }

    /** @return list<int> */
    private function committeeUserIds(Program $program): array
    {
        return DatabaseStatus::hasTable('program_committee')
            ? $program->committee()->whereNotNull('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    /** @param  list<int>  $ids */
    private function syncMeritFor(array $ids): void
    {
        if (! $ids) {
            return;
        }
        $merit = app(MeritService::class);
        User::query()->whereIn('id', $ids)->get()->each(fn (User $u) => $merit->syncUser($u));
    }

    /** Turns a pasted Google Maps / Apple Maps / Waze link or "lat, lng" into a pin. */
    public function resolveMapLink(Request $request): JsonResponse
    {
        $request->validate(['link' => ['required', 'string', 'max:2000']]);
        $point = MapLink::coordinates((string) $request->input('link'));

        return $point
            ? response()->json($point)
            : response()->json(['message' => "Couldn't find a location in that link. Open the place in Google Maps, tap Share, copy the link and paste it here."], 422);
    }

    private function storePoster(Request $request): ?string
    {
        if (! $request->hasFile('poster')) {
            return null;
        }
        $file = $request->file('poster');
        $name = now()->format('Ym').'/'.Str::random(20).'.'.$file->extension();

        return Storage::disk('uploads')->putFileAs('posters', $file, $name) ?: null;
    }
}
