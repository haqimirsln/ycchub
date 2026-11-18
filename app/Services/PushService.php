<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Support\DatabaseStatus;
use App\Support\WebPush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phone notifications. Members turn them on per phone from their profile;
 * notifications are queued and sent by the scheduler every minute, so a busy
 * page never waits on the push services.
 */
class PushService
{
    public const BATCH = 100;

    /** Queued notifications older than this are dropped instead of sent late. */
    public const EXPIRE_HOURS = 12;

    public function ready(): bool
    {
        return WebPush::supported()
            && DatabaseStatus::hasTable('push_subscriptions')
            && DatabaseStatus::hasTable('push_messages');
    }

    /** The site's public key, which phones need to subscribe. Made on first use. */
    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /** @return array{public: string, private: string} */
    private function keys(): array
    {
        $public = Setting::get('push_vapid_public');
        $sealed = Setting::get('push_vapid_private');
        if ($public && $sealed) {
            try {
                return ['public' => $public, 'private' => Crypt::decryptString($sealed)];
            } catch (Throwable) {
                // APP_KEY changed, so the stored key can't be read. Start over below.
            }
        }

        return Cache::lock('ycc.push-keys', 10)->block(5, function () {
            Cache::forget('ycc.settings');
            $public = Setting::get('push_vapid_public');
            $sealed = Setting::get('push_vapid_private');
            if ($public && $sealed) {
                try {
                    return ['public' => $public, 'private' => Crypt::decryptString($sealed)];
                } catch (Throwable) {
                }
            }

            $replacing = (bool) $sealed;
            $keys = WebPush::newKeys();
            Setting::put('push_vapid_public', $keys['public']);
            Setting::put('push_vapid_private', Crypt::encryptString($keys['private']));
            if ($replacing) {
                // Phones subscribed with the old, unreadable key can't receive anything now; they re-subscribe from their profile.
                PushSubscription::query()->delete();
            }

            return $keys;
        });
    }

    private function subject(): string
    {
        $from = (string) config('mail.from.address');

        return 'mailto:'.($from !== '' ? $from : 'noreply@'.(parse_url(url('/'), PHP_URL_HOST) ?: 'localhost'));
    }

    // ---- Subscriptions ---------------------------------------------------------

    /** @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $sub */
    public function subscribe(User $user, array $sub, ?string $device = null): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashOf($sub['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $sub['endpoint'],
                'public_key' => $sub['keys']['p256dh'],
                'auth_token' => $sub['keys']['auth'],
                'device' => $device ? Str::limit($device, 110, '') : null,
            ],
        );
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        PushSubscription::query()->where('user_id', $user->id)->where('endpoint_hash', PushSubscription::hashOf($endpoint))->delete();
    }

    public function deviceCount(User $user): int
    {
        return $this->ready() ? PushSubscription::query()->where('user_id', $user->id)->count() : 0;
    }

    /** @return array{members: int, devices: int, last: ?string} */
    public function stats(): array
    {
        if (! $this->ready()) {
            return ['members' => 0, 'devices' => 0, 'last' => null];
        }

        return [
            'members' => PushSubscription::query()->distinct()->count('user_id'),
            'devices' => PushSubscription::query()->count(),
            'last' => Setting::get('push_last_sent'),
        ];
    }

    // ---- What gets sent --------------------------------------------------------

    /**
     * Queues a notification for each of these members who has notifications on.
     *
     * @param  iterable<int>  $userIds
     * @return int how many members it was queued for
     */
    public function queue(iterable $userIds, string $title, string $body, ?string $url = null, ?string $tag = null): int
    {
        if (! $this->ready()) {
            return 0;
        }
        $ids = collect($userIds)->unique()->values();
        $withPhones = PushSubscription::query()->whereIn('user_id', $ids)->distinct()->pluck('user_id');
        if ($withPhones->isEmpty()) {
            return 0;
        }

        $now = now();
        foreach ($withPhones->chunk(200) as $chunk) {
            PushMessage::query()->insert($chunk->map(fn ($id) => [
                'user_id' => $id,
                'title' => Str::limit($title, 115),
                'body' => Str::limit($body, 235),
                'url' => $url,
                'tag' => $tag,
                'created_at' => $now,
            ])->values()->all());
        }

        return $withPhones->count();
    }

    /** "New event" to every approved member with notifications on, once per event. */
    public function queueNewEvent(Program $program, ?User $except = null): int
    {
        if (! $this->ready() || $program->status !== 'published' || $program->starts_at->isPast()) {
            return 0;
        }

        $ids = User::query()->approved()->when($except, fn ($q) => $q->whereKeyNot($except->id))->pluck('id');
        $count = $this->queue(
            $ids,
            'New event: '.$program->title,
            $program->starts_at->format('D j M, g:i A').' · '.$program->whereLabel().'. Tap to see details and register.',
            route('programs.show', $program),
            'event-'.$program->id,
        );
        if (DatabaseStatus::hasColumn('programs', 'pushed_at')) {
            $program->forceFill(['pushed_at' => now()])->save();
        }

        return $count;
    }

    /** A reminder before an event the member registered for (sent alongside the reminder email). */
    public function queueReminder(ProgramRegistration $reg): int
    {
        $program = $reg->program;
        $when = $program->starts_at->isToday()
            ? 'Today at '.$program->starts_at->format('g:i A')
            : ($program->starts_at->isTomorrow() ? 'Tomorrow at '.$program->starts_at->format('g:i A') : $program->starts_at->format('D j M, g:i A'));

        return $this->queue(
            [$reg->user_id],
            'Reminder: '.$program->title,
            $when.' · '.$program->whereLabel().'. Can\'t make it? Tap to cancel so someone else gets the seat.',
            route('programs.show', $program),
            'reminder-'.$program->id,
        );
    }

    // ---- Sending ---------------------------------------------------------------

    /** Sends queued notifications (cron every minute, or a site visit when there's no cron). Returns how many members were reached. */
    public function sendDue(int $limit = self::BATCH): int
    {
        if (! $this->ready()) {
            return 0;
        }

        // The cron job and a site visit may both try at once; only one sends.
        return Cache::lock('ycc.push-send', 120)->get(fn () => $this->sendBatch($limit)) ?: 0;
    }

    private function sendBatch(int $limit): int
    {
        // A "new event" or reminder that sat in the queue for half a day is no use any more.
        PushMessage::query()->whereNull('sent_at')->where('created_at', '<', now()->subHours(self::EXPIRE_HOURS))->update(['sent_at' => now()]);

        $messages = PushMessage::query()->whereNull('sent_at')->orderBy('id')->limit($limit)->get();
        if ($messages->isEmpty()) {
            return 0;
        }

        // Mark first, so a crash halfway never sends the same notification twice.
        PushMessage::query()->whereIn('id', $messages->pluck('id'))->update(['sent_at' => now()]);

        $phones = PushSubscription::query()->whereIn('user_id', $messages->pluck('user_id')->unique())->get()->groupBy('user_id');
        $reached = 0;
        foreach ($messages as $message) {
            $payload = $this->payload($message->title, $message->body, $message->url, $message->tag);
            $ok = false;
            foreach ($phones->get($message->user_id, collect()) as $phone) {
                $ok = $this->deliver($phone, $payload) || $ok;
            }
            $reached += $ok ? 1 : 0;
        }

        if ($reached) {
            Setting::put('push_last_sent', now()->toDateTimeString());
        }
        // Keep the queue small: sent notifications older than a month aren't needed.
        PushMessage::query()->whereNotNull('sent_at')->where('sent_at', '<', now()->subDays(30))->delete();

        return $reached;
    }

    /**
     * Sends straight away to every phone this member turned notifications on for (the test button).
     *
     * @return array{sent: int, failed: int, error: ?string}
     */
    public function sendNow(User $user, string $title, string $body, ?string $url = null): array
    {
        $sent = $failed = 0;
        $this->lastFailure = null;
        $payload = $this->payload($title, $body, $url, 'test');
        foreach (PushSubscription::query()->where('user_id', $user->id)->get() as $phone) {
            $this->deliver($phone, $payload) ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'error' => $this->lastFailure];
    }

    private function payload(string $title, string $body, ?string $url, ?string $tag): string
    {
        return json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? route('portal'),
            'tag' => $tag,
            'icon' => asset('images/app/icon-192.png'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Why the last delivery in this request failed, for the test button. */
    private ?string $lastFailure = null;

    private function deliver(PushSubscription $phone, string $payload): bool
    {
        $where = $phone->device ?: 'a device';
        try {
            $keys = $this->keys();
            $answer = WebPush::send($phone->endpoint, $phone->public_key, $phone->auth_token, $payload, $keys['public'], $keys['private'], $this->subject());
        } catch (Throwable $e) {
            return $this->failed($phone, "Couldn't reach the push service for {$where}: ".$e->getMessage());
        }

        $status = $answer['status'];
        if ($status >= 200 && $status < 300) {
            $phone->forceFill(['last_sent_at' => now()])->save();

            return true;
        }

        $why = $answer['body'] !== '' ? ' ('.$answer['body'].')' : '';
        // 404/410: the phone uninstalled the app or switched notifications off. Forget it quietly.
        if (in_array($status, [404, 410], true)) {
            $phone->delete();
            $this->lastFailure = "{$where} has turned notifications off, so it was removed. Turn them on again on that phone.";

            return false;
        }

        // Anything else (403 bad signature, 400 bad request, 413, 429, 5xx): keep the phone and record why.
        return $this->failed($phone, "Push service refused {$where} with {$status}{$why}");
    }

    private function failed(PushSubscription $phone, string $message): bool
    {
        $message = mb_substr($message, 0, 255);
        $this->lastFailure = $message;
        Log::warning('Phone notification failed', ['subscription' => $phone->id, 'error' => $message]);
        Setting::put('push_last_error', $message.' · '.now()->format('j M, g:i a'));

        return false;
    }

    /** How many notifications are waiting to go out. */
    public function waiting(): int
    {
        return $this->ready() ? PushMessage::query()->whereNull('sent_at')->count() : 0;
    }
}
