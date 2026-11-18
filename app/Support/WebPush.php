<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends Web Push messages (phone and browser notifications) with nothing but
 * PHP's own OpenSSL, so it runs on shared hosting without extra packages.
 *
 * Message encryption follows RFC 8291 (aes128gcm) and the server identifies
 * itself with VAPID (RFC 8292), which is what Chrome, Firefox, Edge and Safari
 * (iPhone apps added to the home screen) expect.
 */
class WebPush
{
    /** DER header that turns a raw 65-byte P-256 point into a public key OpenSSL can read. */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function supported(): bool
    {
        return extension_loaded('openssl') && function_exists('openssl_pkey_derive') && function_exists('hash_hkdf');
    }

    /**
     * A new VAPID key pair for this site.
     *
     * @return array{public: string, private: string} public key (base64url, raw point) and private key (PEM)
     */
    public static function newKeys(): array
    {
        $key = self::newEcKey();
        openssl_pkey_export($key, $pem);

        return ['public' => self::b64(self::rawPublic($key)), 'private' => $pem];
    }

    /**
     * Sends one message to one subscription.
     *
     * @return array{status: int, body: string} the push service's answer (2xx = accepted; 404 or 410 = this phone is gone)
     */
    public static function send(string $endpoint, string $p256dh, string $auth, string $payload, string $vapidPublic, string $vapidPrivatePem, string $subject, int $ttl = 86400): array
    {
        $body = self::encrypt($payload, $p256dh, $auth);
        $jwt = self::vapidToken($endpoint, $vapidPrivatePem, $subject);

        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => 'vapid t='.$jwt.', k='.$vapidPublic,
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) $ttl,
                'Urgency' => 'normal',
            ])
            ->withBody($body, 'application/octet-stream')
            ->post($endpoint);

        return ['status' => $response->status(), 'body' => mb_substr(trim((string) $response->body()), 0, 300)];
    }

    /**
     * Encrypts a payload for one subscription (RFC 8291, a single aes128gcm record).
     *
     * @param  string  $p256dh  the subscription's public key, base64url
     * @param  string  $auth  the subscription's auth secret, base64url
     */
    public static function encrypt(string $payload, string $p256dh, string $auth, ?string $salt = null): string
    {
        $uaPublic = self::unb64($p256dh);
        $authSecret = self::unb64($auth);
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($authSecret) < 16) {
            throw new RuntimeException('This phone sent an invalid notification key.');
        }

        $local = self::newEcKey();
        $asPublic = self::rawPublic($local);
        $shared = openssl_pkey_derive(self::publicKeyFromRaw($uaPublic), $local, 32);
        if ($shared === false) {
            throw new RuntimeException('Could not set up encryption for this phone.');
        }

        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $salt ??= random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // One record holding the whole message; 0x02 marks it as the last record.
        $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt the notification.');
        }

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /** A signed VAPID token (JWT, ES256) for the push service behind this endpoint. */
    public static function vapidToken(string $endpoint, string $privatePem, string $subject, ?int $expires = null): string
    {
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        $header = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64(json_encode(['aud' => $audience, 'exp' => $expires ?? time() + 12 * 3600, 'sub' => $subject], JSON_UNESCAPED_SLASHES));

        $key = openssl_pkey_get_private($privatePem);
        if ($key === false || ! openssl_sign("$header.$claims", $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the notification request.');
        }

        return "$header.$claims.".self::b64(self::derToRaw($der));
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(string $text): string
    {
        $text = strtr(trim($text), '-_', '+/');

        return (string) base64_decode($text.str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }

    /** @return \OpenSSLAsymmetricKey */
    public static function newEcKey()
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new RuntimeException('This server can\'t create notification keys (OpenSSL without EC support).');
        }

        return $key;
    }

    /** The uncompressed point (0x04 || X || Y) of an EC key. */
    public static function rawPublic($key): string
    {
        $ec = openssl_pkey_get_details($key)['ec'];

        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** @return \OpenSSLAsymmetricKey */
    public static function publicKeyFromRaw(string $raw)
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX).$raw), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('This phone sent an invalid notification key.');
        }

        return $key;
    }

    /** OpenSSL signs ECDSA as DER; JWT wants the two 32-byte numbers side by side. */
    private static function derToRaw(string $der): string
    {
        $offset = 2;
        if (ord($der[1]) & 0x80) {
            $offset += ord($der[1]) & 0x7F;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $len);
            $parts[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $len;
        }

        return $parts[0].$parts[1];
    }
}
