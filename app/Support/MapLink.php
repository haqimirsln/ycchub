<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pulls a latitude/longitude out of what an exco pastes: a Google Maps link
 * (long or maps.app.goo.gl short link), an Apple Maps / Waze link, or plain
 * coordinates like "5.6731, 102.7189".
 */
class MapLink
{
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.co', 'maps.google.com'];

    /** @return array{lat: float, lng: float}|null */
    public static function coordinates(string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if ($found = self::parse($input)) {
            return $found;
        }

        // Short links only reveal the place after following the redirect.
        $host = strtolower((string) parse_url($input, PHP_URL_HOST));
        if (str_starts_with($input, 'https://') && in_array($host, self::SHORT_HOSTS, true)) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
                        'Accept-Language' => 'en',
                    ])
                    ->withOptions(['allow_redirects' => ['max' => 6]])
                    ->get($input);
                $final = (string) $response->effectiveUri();

                return self::parse($final) ?? self::parse(substr($response->body(), 0, 200000));
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /** @return array{lat: float, lng: float}|null */
    public static function parse(string $text): ?array
    {
        $text = urldecode($text);
        $num = '(-?\d{1,3}\.\d{2,})';
        $patterns = [
            "/!3d{$num}!4d{$num}/",                                   // exact place in Google links
            "/@{$num},{$num}/",                                       // map centre in Google links
            "/[?&](?:q|query|ll|destination|daddr|center|sll)={$num},\s*{$num}/", // Google, Apple, Waze
            "/^\s*{$num}\s*,\s*{$num}\s*$/",                          // plain "lat, lng"
            "/\/{$num},\s*{$num}/",                                   // .../place/5.67,102.71
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ! ($lat == 0.0 && $lng == 0.0)) {
                    return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
                }
            }
        }

        return null;
    }
}
