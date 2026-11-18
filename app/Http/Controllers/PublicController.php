<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Services\InstagramFeedService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function home(InstagramFeedService $instagram): View
    {
        $upcoming = Program::query()->published()->upcoming()->withSeatCounts()->limit(3)->get();

        // Public numbers are cached briefly; they don't need to be real-time.
        $numbers = Cache::remember('ycc.public-numbers', now()->addMinutes(30), fn () => [
            'members' => User::query()->tracked()->count(),
            'programs' => Program::query()->published()->where('starts_at', '<', now())->count(),
            'attendances' => (int) User::query()->tracked()->sum('total_attended'),
        ]);

        return view('public.home', [
            'upcoming' => $upcoming,
            'numbers' => $numbers,
            'posts' => $instagram->postsForDisplay(9),
            'social' => self::social(),
            'mt' => ($mt = app(\App\Services\MtService::class))->ready() && $mt->isPublic()
                ? ['session' => $mt->session(), 'advisor' => $mt->advisor(), 'terms' => $mt->current()->filter(fn ($t) => $t->exists)]
                : null,
        ]);
    }

    /** Social links, with admin overrides from the settings table. */
    public static function social(): array
    {
        return [
            'instagram_handle' => Setting::get('instagram_handle', config('ycc.social.instagram_handle')),
            'instagram_url' => Setting::get('instagram_url', config('ycc.social.instagram_url')),
            'linkedin_url' => Setting::get('linkedin_url', config('ycc.social.linkedin_url')),
        ];
    }
}
