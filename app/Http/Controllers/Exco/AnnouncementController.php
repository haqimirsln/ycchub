<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Program;
use App\Notifications\ClubAnnouncement;
use App\Services\AnnouncementService;
use App\Support\Activity;
use App\Support\EmailHtml;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Email members: promotions, news and reminders from the MT. */
class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $service) {}

    public function index(): View
    {
        $ready = $this->service->ready();

        return view('exco.announcements.index', [
            'ready' => $ready,
            'templates' => AnnouncementService::templates(),
            'events' => Program::query()->where('status', 'published')->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->get(['id', 'title', 'starts_at']),
            'history' => $ready ? Announcement::query()->with('sender:id,full_name')->latest('id')->limit(20)->get() : collect(),
            'everyone' => $ready ? $this->service->recipients([])->count() : 0,
            'optedOut' => $ready ? \App\Models\User::query()->approved()->where('email_opt_out', true)->count() : 0,
        ]);
    }

    /** Live "this goes to N members" count for the form. */
    public function count(Request $request): JsonResponse
    {
        abort_unless($this->service->ready(), 409);

        return response()->json(['count' => $this->service->recipients($request->all())->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->service->ready(), 409, 'Press Update database in Settings first.');
        $announcement = $this->fromRequest($request);

        if ($request->input('action') === 'test') {
            try {
                $request->user()->notify(new ClubAnnouncement($announcement));
            } catch (Throwable $e) {
                Log::error('Test club email failed', ['error' => $e->getMessage()]);

                return back()->withInput()->with('error', 'Test email failed: '.$e->getMessage());
            }

            return back()->withInput()->with('success', "Test sent to {$request->user()->email}. Check it, then press Send when you're happy.");
        }

        $announcement->created_by = $request->user()->id;
        $this->service->start($announcement);
        Activity::log('system.email', "Emailed \"{$announcement->subject}\" to {$announcement->recipient_count} members ({$announcement->audience_label})", $announcement);

        if ($announcement->recipient_count === 0) {
            return back()->withInput()->with('error', 'Nobody matches that audience, so nothing was sent.');
        }
        $left = $announcement->remaining();

        return redirect()->route('exco.announcements.index')->with('success', $left
            ? "Sent {$announcement->sent_count} of {$announcement->recipient_count}. The other {$left} go out ".AnnouncementService::BATCH.' a minute.'
            : "Sent to {$announcement->sent_count} ".($announcement->sent_count === 1 ? 'member' : 'members').'.');
    }

    /** Shows the email as it will look, addressed to you. Nothing is saved or sent. */
    public function preview(Request $request): Response
    {
        return response((string) (new ClubAnnouncement($this->fromRequest($request)))->toMail($request->user())->render());
    }

    public function next(Announcement $announcement): RedirectResponse
    {
        $sent = $this->service->sendBatch($announcement);
        $left = $announcement->fresh()->remaining();

        return back()->with('success', "Sent {$sent} more.".($left ? " {$left} left." : ' All done.'));
    }

    public function cancel(Announcement $announcement): RedirectResponse
    {
        $left = $this->service->cancel($announcement);
        Activity::log('system.email', "Stopped \"{$announcement->subject}\" ({$left} not sent)", $announcement);

        return back()->with('success', "Stopped. {$left} ".($left === 1 ? 'email was' : 'emails were').' not sent.');
    }

    /** A picture added in the editor. Shrunk to email size and kept in public/uploads/email. */
    public function image(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ], [
            'image.max' => 'Pictures must be 8 MB or smaller.',
            'image.mimes' => 'Use a JPG, PNG, WebP or GIF picture.',
        ]);

        $file = $request->file('image');
        $name = 'email/'.now()->format('Ym').'/'.Str::random(24);
        if ($file->extension() === 'gif') {
            // Keep animated GIFs as they are.
            $path = Storage::disk('uploads')->putFileAs(dirname($name), $file, basename($name).'.gif');
        } else {
            $jpeg = $this->emailJpeg($file->getRealPath());
            if ($jpeg === null) {
                return response()->json(['message' => "Couldn't read that picture. Try another one."], 422);
            }
            $path = $name.'.jpg';
            Storage::disk('uploads')->put($path, $jpeg);
        }

        return response()->json(['url' => asset('uploads/'.$path)]);
    }

    /** At most 1200px wide, JPEG, upright: small enough for inboxes, sharp on phones. */
    private function emailJpeg(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return file_get_contents($path) ?: null;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            return null;
        }
        if (function_exists('exif_read_data')) {
            $angle = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data($path)['Orientation'] ?? 1)] ?? 0;
            if ($angle) {
                $src = imagerotate($src, $angle, 0) ?: $src;
            }
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = min(1200, $w);
        $nh = max(1, (int) round($h * $nw / $w));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);

        return ob_get_clean() ?: null;
    }

    private function fromRequest(Request $request): Announcement
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:60000'],
            'button_label' => ['nullable', 'required_with:button_url', 'string', 'max:40'],
            'button_url' => ['nullable', 'required_with:button_label', 'url', 'max:500'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'roles' => ['nullable', 'array'],
            'teams' => ['nullable', 'array'],
            'statuses' => ['nullable', 'array'],
            'types' => ['nullable', 'array'],
        ], [
            'button_label.required_with' => 'Give the button a label, e.g. "Register now".',
            'button_url.required_with' => 'Paste the link the button opens.',
            'button_url.url' => 'Paste the full link, starting with https://',
        ]);

        $body = EmailHtml::clean($data['body']);
        if (EmailHtml::isEmpty($body)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['body' => 'Write a message first.']);
        }

        return new Announcement([
            'subject' => trim($data['subject']),
            'body' => $body,
            'button_label' => $data['button_label'] ?? null,
            'button_url' => $data['button_url'] ?? null,
            'program_id' => $data['program_id'] ?? null,
            'audience' => $this->service->audience($request->all()),
        ]);
    }
}
