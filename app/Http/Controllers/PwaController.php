<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Makes YCC Hub installable as an app (Add to Home Screen / Install app).
 * The manifest and service worker are served by Laravel rather than as static
 * files so their URLs are correct whether the site runs on a domain or in a
 * subfolder such as conservems.my/ycc-hub.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $base = rtrim(url('/'), '/').'/';

        return response()->json([
            'id' => $base,
            'name' => 'YCC Hub',
            'short_name' => 'YCC Hub',
            'description' => 'Young Conservationist Club, UMT. Join events and track your participation.',
            'start_url' => route('portal'),
            'scope' => $base,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0a1e24',
            'theme_color' => '#0a1e24',
            'lang' => 'en',
            'categories' => ['education', 'social'],
            'icons' => [
                ['src' => asset('images/app/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('images/app/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('images/app/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Events', 'url' => route('programs.index')],
                ['name' => 'My profile', 'url' => route('profile.edit')],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }

    public function serviceWorker(): Response
    {
        // A new build changes this version, which makes phones drop the old cached files.
        $buildManifest = public_path('build/manifest.json');
        $version = substr(md5((is_file($buildManifest) ? (string) filemtime($buildManifest) : '0').'|2'), 0, 10);

        $js = view('pwa.sw', [
            'version' => $version,
            'offlineUrl' => route('pwa.offline'),
            'base' => rtrim(url('/'), '/').'/',
            'precache' => [
                route('pwa.offline'),
                asset('images/app/icon-192.png'),
                asset('images/ycc-crest-192.png'),
            ],
        ])->render();

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function offline(): View
    {
        return view('pwa.offline');
    }

    public function guide(): View
    {
        return view('pwa.guide');
    }
}
