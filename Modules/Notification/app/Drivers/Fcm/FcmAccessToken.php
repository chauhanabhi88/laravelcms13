<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers\Fcm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth access token for the FCM HTTP v1 API from a Google service account JSON file:
 * a self-signed RS256 JWT (openssl_sign) exchanged at the token endpoint, cached ~50 minutes.
 */
class FcmAccessToken
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'notification.fcm.access_token';

    /**
     * @param  array{credentials_path?: string|null, token_uri?: string, token_cache_seconds?: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function get(): string
    {
        return (string) Cache::remember(self::CACHE_KEY, (int) ($this->config['token_cache_seconds'] ?? 3000), fn (): string => $this->fetch());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Project id from the service account file (used when FCM_PROJECT_ID is not set).
     */
    public function projectId(): ?string
    {
        return $this->credentials()['project_id'] ?? null;
    }

    /**
     * The signed assertion; public for tests.
     */
    public function assertion(?int $now = null): string
    {
        $credentials = $this->credentials();
        $now ??= time();

        if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('The FCM service account file has no client_email / private_key.');
        }

        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'] ?? $this->tokenUri(),
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM assertion with the service account key.');
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    protected function fetch(): string
    {
        $response = Http::integration('fcm')->asForm()->post($this->tokenUri(), [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion(),
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('FCM token request failed with HTTP '.$response->status().'.');
        }

        return $token;
    }

    /**
     * @return array<string, string>
     */
    protected function credentials(): array
    {
        $path = (string) ($this->config['credentials_path'] ?? '');

        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('FCM_CREDENTIALS_PATH does not point to a service account file.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials)) {
            throw new RuntimeException('The FCM service account file is not valid JSON.');
        }

        return $credentials;
    }

    private function tokenUri(): string
    {
        return (string) ($this->config['token_uri'] ?? 'https://oauth2.googleapis.com/token');
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
