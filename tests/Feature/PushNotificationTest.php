<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Services\EventReminderService;
use App\Services\PushService;
use App\Support\Scheduler;
use App\Support\WebPush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Phone notifications (Web Push): encryption, turning them on and off, and what gets sent. */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** A phone's side of a subscription: its private key (to decrypt) and what it sends us. */
    private function fakePhone(string $endpoint = 'https://fcm.googleapis.com/fcm/send/phone-1'): array
    {
        $key = WebPush::newEcKey();

        return [
            'private' => $key,
            'sub' => ['endpoint' => $endpoint, 'keys' => ['p256dh' => WebPush::b64(WebPush::rawPublic($key)), 'auth' => WebPush::b64(random_bytes(16))]],
        ];
    }

    /** Decrypts like a phone would (RFC 8291), to prove the message is readable. */
    private function decrypt(string $body, $phoneKey, string $auth): string
    {
        $salt = substr($body, 0, 16);
        $idLen = ord($body[20]);
        $serverPublic = substr($body, 21, $idLen);
        $record = substr($body, 21 + $idLen);

        $shared = openssl_pkey_derive(WebPush::publicKeyFromRaw($serverPublic), $phoneKey, 32);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".WebPush::rawPublic($phoneKey).$serverPublic, WebPush::unb64($auth));
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($record, -16));

        $this->assertNotFalse($plain, 'the phone could not decrypt the message');
        $this->assertSame("\x02", substr($plain, -1), 'last-record marker');

        return substr($plain, 0, -1);
    }

    public function test_messages_are_encrypted_so_only_the_phone_can_read_them(): void
    {
        $this->assertTrue(WebPush::supported());
        $phone = $this->fakePhone();

        $body = WebPush::encrypt('{"title":"Mangrove Day ✓"}', $phone['sub']['keys']['p256dh'], $phone['sub']['keys']['auth']);

        $this->assertSame('{"title":"Mangrove Day ✓"}', $this->decrypt($body, $phone['private'], $phone['sub']['keys']['auth']));
        $other = $this->fakePhone();
        $this->assertNotSame($body, WebPush::encrypt('{"title":"Mangrove Day ✓"}', $other['sub']['keys']['p256dh'], $other['sub']['keys']['auth']));
    }

    public function test_vapid_token_is_signed_for_the_push_service(): void
    {
        $keys = WebPush::newKeys();
        [$h, $c, $s] = explode('.', WebPush::vapidToken('https://web.push.apple.com/abc', $keys['private'], 'mailto:ycc@example.com'));

        $claims = json_decode(WebPush::unb64($c), true);
        $this->assertSame('https://web.push.apple.com', $claims['aud']);
        $this->assertSame('mailto:ycc@example.com', $claims['sub']);
        $this->assertGreaterThan(time(), $claims['exp']);

        // Rebuild the DER signature OpenSSL expects and check it with the public key.
        $raw = WebPush::unb64($s);
        $this->assertSame(64, strlen($raw));
        $int = fn ($b) => ltrim($b, "\0") === '' ? "\0" : ((ord(ltrim($b, "\0")[0]) & 0x80) ? "\0".ltrim($b, "\0") : ltrim($b, "\0"));
        [$r, $sv] = [$int(substr($raw, 0, 32)), $int(substr($raw, 32))];
        $der = "\x30".chr(4 + strlen($r) + strlen($sv))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($sv)).$sv;
        $this->assertSame(1, openssl_verify("$h.$c", $der, WebPush::publicKeyFromRaw(WebPush::unb64($keys['public'])), OPENSSL_ALGO_SHA256));
    }

    public function test_member_turns_notifications_on_and_off_for_a_phone(): void
    {
        $member = User::factory()->create();
        $phone = $this->fakePhone();

        $key = $this->actingAs($member)->getJson(route('push.key'))->assertOk()->json('key');
        $this->assertSame(65, strlen(WebPush::unb64($key)));
        $this->assertStringNotContainsString('PRIVATE KEY', (string) Setting::get('push_vapid_private'), 'the private key is stored encrypted');
        $this->assertSame($key, $this->actingAs($member)->getJson(route('push.key'))->json('key'), 'the key stays the same');

        $this->actingAs($member)->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0')
            ->postJson(route('push.subscribe'), $phone['sub'])->assertOk()->assertJson(['devices' => 1]);
        $this->actingAs($member)->postJson(route('push.subscribe'), $phone['sub'])->assertOk()->assertJson(['devices' => 1]);
        $this->assertSame('Android phone (Chrome)', PushSubscription::first()->device);

        $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertSee('Phone notifications');

        $this->actingAs($member)->postJson(route('push.unsubscribe'), ['endpoint' => $phone['sub']['endpoint']])->assertOk()->assertJson(['devices' => 0]);
        $this->assertSame(0, PushSubscription::count());

        $this->actingAs($member)->postJson(route('push.subscribe'), ['endpoint' => 'http://insecure.example/x', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])
            ->assertStatus(422);
    }

    public function test_publishing_an_event_notifies_phones_once(): void
    {
        $mt = User::factory()->admin()->create();
        $withPhone = User::factory()->create();
        $noPhone = User::factory()->create();
        $push = app(PushService::class);
        $push->subscribe($withPhone, $this->fakePhone()['sub']);
        $push->subscribe($mt, $this->fakePhone('https://fcm.googleapis.com/fcm/send/mt')['sub']);

        $payload = [
            'title' => 'Mangrove Day', 'description' => 'Planting.', 'category' => 'talk', 'venue' => 'Setiu',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'counts_for_activity' => '1', 'notify_push' => '1',
        ];

        // A draft never notifies.
        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'status' => 'draft'])->assertSessionHasNoErrors();
        $this->assertSame(0, PushMessage::count());

        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'status' => 'published'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Phone notification going to 1 member.'));
        $program = Program::query()->where('status', 'published')->first();
        $this->assertNotNull($program->pushed_at);
        $this->assertSame([$withPhone->id], PushMessage::pluck('user_id')->all(), 'only members with phones, and not the MT who posted it');
        $this->assertStringStartsWith('New event: Mangrove Day', PushMessage::first()->title);

        // Editing later doesn't send it again.
        $this->actingAs($mt)->put(route('exco.programs.update', $program), [...$payload, 'status' => 'published', 'title' => 'Mangrove Day 2'])->assertSessionHasNoErrors();
        $this->assertSame(1, PushMessage::count());
        $this->actingAs($mt)->get(route('exco.programs.edit', $program))->assertOk()->assertSee('Phone notification sent on');

        // Unticked: nothing.
        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'status' => 'published', 'notify_push' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(1, PushMessage::count());
        $this->assertFalse($noPhone->is($withPhone));
    }

    public function test_queued_notifications_are_sent_and_dead_phones_are_forgotten(): void
    {
        $member = User::factory()->create();
        $push = app(PushService::class);
        $good = $this->fakePhone('https://fcm.googleapis.com/fcm/send/good');
        $gone = $this->fakePhone('https://updates.push.services.mozilla.com/wpush/v2/gone');
        $push->subscribe($member, $good['sub']);
        $push->subscribe($member, $gone['sub']);

        $sent = [];
        Http::fake(function ($request) use (&$sent, $good) {
            $sent[] = $request;
            if (str_contains($request->url(), 'gone')) {
                return Http::response('', 410);
            }
            // The real push service would get a body only this phone can read.
            $this->assertStringStartsWith('vapid t=', $request->header('Authorization')[0]);
            $this->assertSame('aes128gcm', $request->header('Content-Encoding')[0]);
            $json = json_decode($this->decrypt($request->body(), $good['private'], $good['sub']['keys']['auth']), true);
            $this->assertSame('Hello', $json['title']);

            return Http::response('', 201);
        });

        $this->assertSame(1, $push->queue([$member->id], 'Hello', 'From YCC', route('portal'), 'test'));
        $this->assertSame(1, $push->sendDue());
        $this->assertCount(2, $sent);
        $this->assertNotNull(PushMessage::first()->sent_at);
        $this->assertSame(['https://fcm.googleapis.com/fcm/send/good'], PushSubscription::pluck('endpoint')->all(), 'the 410 phone is removed');
        $this->assertSame(0, $push->sendDue(), 'nothing sent twice');
    }

    public function test_event_reminders_also_go_to_phones_and_the_test_button_works(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $member = User::factory()->create();
        app(PushService::class)->subscribe($member, $this->fakePhone()['sub']);
        $program = Program::factory()->create(['title' => 'Turtle Night', 'starts_at' => now()->addHours(20), 'status' => 'published']);
        ProgramRegistration::create(['program_id' => $program->id, 'user_id' => $member->id, 'status' => 'registered', 'registered_at' => now()->subDay()]);

        app(EventReminderService::class)->sendDue();
        $msg = PushMessage::first();
        $this->assertSame('Reminder: Turtle Night', $msg->title);
        $this->assertSame(route('programs.show', $program), $msg->url);

        $this->actingAs($member)->postJson(route('push.test'))->assertOk()->assertJson(['sent' => 1]);

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->postJson(route('push.test'))->assertStatus(422);
    }

    public function test_without_cron_site_visits_send_the_queue_and_stale_ones_are_dropped(): void
    {
        $sent = 0;
        Http::fake(function () use (&$sent) {
            $sent++;

            return Http::response('', 201);
        });
        $member = User::factory()->create();
        $push = app(PushService::class);
        $push->subscribe($member, $this->fakePhone()['sub']);

        // One stuck from days ago (like before the fix), one fresh.
        $push->queue([$member->id], 'New event: Meeting Malam ni', 'Old');
        PushMessage::query()->update(['created_at' => now()->subDays(5)]);
        $push->queue([$member->id], 'New event: Beach Cleanup', 'Fresh');

        // Cron ran a minute ago and its jobs work: site visits leave the work to it.
        Scheduler::markRan();
        Scheduler::job('ycc:send-push', fn () => null);
        Scheduler::fallback();
        $this->assertSame(0, $sent);

        // No cron: a visit sends what's waiting, once a minute at most.
        Cache::forget('ycc.cron-last');
        Cache::forget('ycc.job-ok.ycc:send-push');
        Scheduler::fallback();
        $this->assertSame(1, $sent, 'only the fresh notification goes out');
        $this->assertSame(0, PushMessage::whereNull('sent_at')->count(), 'the stale one is dropped, not sent late');

        $push->queue([$member->id], 'New event: Reef Survey', 'Next');
        Scheduler::fallback();
        $this->assertSame(1, $sent, 'not again within the same minute');
    }

    public function test_settings_show_whether_the_cron_job_runs(): void
    {
        $super = User::factory()->superadmin()->create();

        $this->actingAs($super)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Not set up yet.')->assertSee(base_path('artisan').' schedule:run', false);

        // Heartbeat ticks but the jobs fail (no proc_open on the host): say so, don't claim "Running".
        Scheduler::markRan();
        Scheduler::job('ycc:send-push', fn () => throw new \LogicException('The Process class relies on proc_open'));
        $this->actingAs($super)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('its jobs aren’t finishing', false)->assertSee('relies on proc_open')->assertDontSee('Running. Last ran');

        Scheduler::job('ycc:send-push', fn () => null);
        $this->actingAs($super)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Running. Last ran')->assertDontSee('Not set up yet.');
    }

    public function test_scheduled_jobs_never_need_proc_open(): void
    {
        // Schedule::command() starts a new process (proc_open), which shared hosting disables.
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertInstanceOf(\Illuminate\Console\Scheduling\CallbackEvent::class, $event, ($event->description ?? $event->command ?? '?').' would need proc_open');
        }

        // And the job wrapper really sends what's queued.
        Http::fake(['*' => Http::response('', 201)]);
        $member = User::factory()->create();
        app(PushService::class)->subscribe($member, $this->fakePhone()['sub']);
        app(PushService::class)->queue([$member->id], 'Hello', 'From cron');
        \Illuminate\Support\Facades\Artisan::call('ycc:send-push');
        $this->assertSame(0, PushMessage::whereNull('sent_at')->count());
    }

    public function test_a_refusal_keeps_the_phone_and_says_why(): void
    {
        Http::fake(['*' => Http::response('{"reason":"BadJwtToken"}', 403)]);
        $member = User::factory()->create();
        app(PushService::class)->subscribe($member, $this->fakePhone('https://web.push.apple.com/abc')['sub'] + []);
        PushSubscription::query()->update(['device' => 'iPhone (Safari)']);

        $this->actingAs($member)->postJson(route('push.test'))->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '403') && str_contains($m, 'BadJwtToken') && str_contains($m, 'iPhone'));
        $this->assertSame(1, PushSubscription::count(), 'a 403 is our problem, not the phone’s: keep it');
        $this->assertStringContainsString('BadJwtToken', (string) Setting::get('push_last_error'));
    }

    public function test_publishing_says_why_no_notification_went_out(): void
    {
        $mt = User::factory()->admin()->create();
        $payload = ['title' => 'Late one', 'description' => 'x', 'category' => 'talk', 'venue' => 'Setiu', 'counts_for_activity' => '1', 'notify_push' => '1', 'status' => 'published'];

        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'starts_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'no other members have notifications turned on'));
        $this->actingAs($mt)->post(route('exco.programs.store'), [...$payload, 'starts_at' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'already started'));
    }

    public function test_the_app_can_get_a_fresh_security_token(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->getJson(route('csrf.token'))->assertOk()->assertJsonStructure(['token']);
        $this->actingAs($member)->get(route('profile.edit'))->assertSee(route('csrf.token'), false);
    }
}
