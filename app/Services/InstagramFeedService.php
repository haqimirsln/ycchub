<?php

namespace App\Services;

use App\Models\InstagramPost;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pulls the club's latest Instagram posts through the Instagram API with
 * Instagram Login and caches them (with a local copy of each image, because
 * Instagram CDN URLs expire). The landing page only ever reads the cache, so a
 * slow or failing Instagram API never slows the site down.
 *
 * Requirements: @young.conservationist must be a Professional (Business or
 * Creator) account, and an admin pastes a long-lived access token into
 * Admin > Settings. The token is refreshed weekly by the scheduler.
 */
class InstagramFeedService
{
    public function token(): ?string
    {
        return Setting::get('instagram_access_token', config('ycc.instagram.access_token'));
    }

    public function isConfigured(): bool
    {
        return filled($this->token());
    }

    /** Posts for the landing page: API posts if we have any, otherwise manually pinned post URLs. */
    public function postsForDisplay(int $limit = 9): Collection
    {
        $api = InstagramPost::query()->where('source', 'api')->orderByDesc('posted_at')->limit($limit)->get();
        if ($api->isNotEmpty()) {
            return $api;
        }

        return InstagramPost::query()->where('source', 'manual')->orderByDesc('posted_at')->orderByDesc('id')->limit($limit)->get();
    }

    /** @return int number of posts stored */
    public function sync(): int
    {
        $token = $this->token();
        if (! $token) {
            return 0;
        }

        try {
            $response = Http::timeout(20)->retry(2, 500, throw: false)->get(rtrim(config('ycc.instagram.graph_url'), '/').'/me/media', [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
                'limit' => config('ycc.instagram.post_limit', 12),
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                $message = $response->json('error.message') ?? ('HTTP '.$response->status());
                $this->recordError($message);

                return 0;
            }

            $keep = [];
            foreach ($response->json('data', []) as $item) {
                $imageSource = ($item['media_type'] ?? '') === 'VIDEO'
                    ? ($item['thumbnail_url'] ?? null)
                    : ($item['media_url'] ?? null);

                $post = InstagramPost::query()->firstOrNew(['ig_id' => $item['id']]);
                $post->fill([
                    'source' => 'api',
                    'media_type' => $item['media_type'] ?? null,
                    'caption' => $item['caption'] ?? null,
                    'permalink' => $item['permalink'] ?? config('ycc.social.instagram_url'),
                    'posted_at' => isset($item['timestamp']) ? Carbon::parse($item['timestamp'])->timezone(config('app.timezone')) : now(),
                ]);

                if ($imageSource && (! $post->image_path || ! Storage::disk('uploads')->exists($post->image_path))) {
                    $post->image_path = $this->downloadImage($item['id'], $imageSource) ?? $post->image_path;
                }
                $post->save();
                $keep[] = $item['id'];
            }

            // Drop API posts that fell out of the latest window.
            InstagramPost::query()->where('source', 'api')->whereNotIn('ig_id', $keep)->get()
                ->each(function (InstagramPost $old) {
                    if ($old->image_path) {
                        Storage::disk('uploads')->delete($old->image_path);
                    }
                    $old->delete();
                });

            Setting::put('instagram_last_sync_at', now()->toDateTimeString());
            Setting::put('instagram_last_error', null);

            return count($keep);
        } catch (Throwable $e) {
            $this->recordError($e->getMessage());

            return 0;
        }
    }

    /** Long-lived tokens last 60 days; refreshing resets the clock. */
    public function refreshToken(): bool
    {
        $token = $this->token();
        if (! $token) {
            return false;
        }

        try {
            $response = Http::timeout(20)->get(rtrim(config('ycc.instagram.graph_url'), '/').'/refresh_access_token', [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $token,
            ]);

            if ($response->failed() || ! $response->json('access_token')) {
                $this->recordError('Token refresh failed: '.($response->json('error.message') ?? 'HTTP '.$response->status()));

                return false;
            }

            Setting::put('instagram_access_token', $response->json('access_token'));
            Setting::put('instagram_token_expires_at', now()->addSeconds((int) $response->json('expires_in', 5184000))->toDateTimeString());

            return true;
        } catch (Throwable $e) {
            $this->recordError('Token refresh failed: '.$e->getMessage());

            return false;
        }
    }

    /** Accepts instagram.com/p/... or /reel/... links, one per line. */
    public function savePinnedUrls(string $raw): int
    {
        $urls = collect(preg_split('/\R/', $raw))
            ->map(fn ($u) => trim($u))
            ->filter(fn ($u) => preg_match('#^https://(www\.)?instagram\.com/(p|reel|tv)/[A-Za-z0-9_-]+/?#', $u))
            ->map(fn ($u) => preg_replace('#\?.*$#', '', $u))
            ->unique()
            ->values();

        InstagramPost::query()->where('source', 'manual')->delete();
        $count = $urls->count();
        foreach ($urls as $i => $url) {
            InstagramPost::create([
                'source' => 'manual',
                'permalink' => $url,
                // Keep the admin's order: first line shows first.
                'posted_at' => now()->subMinutes($i),
            ]);
        }

        return $count;
    }

    private function downloadImage(string $igId, string $url): ?string
    {
        try {
            $res = Http::timeout(20)->get($url);
            if ($res->failed()) {
                return null;
            }
            $path = 'instagram/'.preg_replace('/[^A-Za-z0-9_-]/', '', $igId).'.jpg';
            Storage::disk('uploads')->put($path, $res->body());

            return $path;
        } catch (Throwable $e) {
            Log::warning('Instagram image download failed', ['id' => $igId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function recordError(string $message): void
    {
        Log::warning('Instagram feed: '.$message);
        Setting::put('instagram_last_error', now()->toDateTimeString().' - '.mb_substr($message, 0, 400));
    }
}
