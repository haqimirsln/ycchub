<?php

namespace App\Http\Controllers;

use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Turning phone notifications on and off for the phone the member is using. */
class PushController extends Controller
{
    public function __construct(private PushService $push) {}

    public function key(): JsonResponse
    {
        abort_unless($this->push->ready(), 503, 'Phone notifications are not set up yet.');

        return response()->json(['key' => $this->push->publicKey()]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        abort_unless($this->push->ready(), 503, 'Phone notifications are not set up yet.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:120'],
            'keys.auth' => ['required', 'string', 'max:60'],
        ]);

        $this->push->subscribe($request->user(), $data, $this->deviceName((string) $request->userAgent()));

        return response()->json(['ok' => true, 'devices' => $this->push->deviceCount($request->user())]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        if ($this->push->ready()) {
            $this->push->unsubscribe($request->user(), $data['endpoint']);
        }

        return response()->json(['ok' => true, 'devices' => $this->push->deviceCount($request->user())]);
    }

    public function test(Request $request): JsonResponse
    {
        abort_unless($this->push->ready(), 503, 'Phone notifications are not set up yet.');

        $result = $this->push->sendNow(
            $request->user(),
            'YCC Hub notifications are on',
            'This is how event reminders and new events will show up on this phone.',
            route('portal'),
        );

        $total = $result['sent'] + $result['failed'];
        $message = match (true) {
            $total === 0 => 'This account has no phones with notifications on. Tap “Turn on for this phone” first.',
            $result['failed'] === 0 => "Sent to {$result['sent']} ".($result['sent'] === 1 ? 'device' : 'devices').'. It should show up in a few seconds. If it doesn’t, check that notifications for YCC Hub are allowed in your phone’s settings and Focus is off.',
            $result['sent'] > 0 => "Sent to {$result['sent']} of {$total} devices. One failed: {$result['error']}",
            default => 'Not sent: '.($result['error'] ?? 'unknown error.'),
        };

        return response()->json($result + ['message' => $message], $result['sent'] ? 200 : 422);
    }

    /** "iPhone", "Android phone" or "Chrome on Windows", so members can tell their phones apart. */
    private function deviceName(string $ua): string
    {
        $device = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android phone',
            str_contains($ua, 'Windows') => 'Windows computer',
            str_contains($ua, 'Macintosh') => 'Mac',
            default => 'Device',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        return $browser ? "$device ($browser)" : $device;
    }
}
