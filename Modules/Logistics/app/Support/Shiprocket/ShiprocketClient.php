<?php

declare(strict_types=1);

namespace Modules\Logistics\Support\Shiprocket;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Logistics\Exceptions\CourierException;

/**
 * Shiprocket REST over Http::integration('shiprocket') (plan M13 §5.3). The bearer token from
 * POST /v1/external/auth/login is valid ~10 days and cached for 9; a 401 drops it, logs in
 * again and repeats the request once.
 */
class ShiprocketClient
{
    public function __construct(
        private readonly ?string $email,
        private readonly ?string $password,
        private readonly string $baseUrl,
    ) {}

    /**
     * A client for the .env API user (a courier row may pass its own credentials).
     */
    public static function fromConfig(?string $email = null, ?string $password = null): self
    {
        return new self(
            $email ?? config('logistics.shiprocket.email'),
            $password ?? config('logistics.shiprocket.password'),
            rtrim((string) config('logistics.shiprocket.base_url', 'https://apiv2.shiprocket.in'), '/'),
        );
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->email) !== '' && trim((string) $this->password) !== '';
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws CourierException
     */
    public function get(string $path, array $query = [], ?float $timeoutSeconds = null): array
    {
        return $this->send('get', $path, $query, $timeoutSeconds);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CourierException
     */
    public function post(string $path, array $data = [], ?float $timeoutSeconds = null): array
    {
        return $this->send('post', $path, $data, $timeoutSeconds);
    }

    /**
     * The cached bearer token, logging in when there is none.
     *
     * @throws CourierException
     */
    public function token(): string
    {
        return (string) Cache::remember($this->tokenCacheKey(), now()->addDays((int) config('logistics.shiprocket.token_cache_days', 9)), fn (): string => $this->login());
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CourierException
     */
    protected function send(string $method, string $path, array $data, ?float $timeoutSeconds, bool $retried = false): array
    {
        if (! $this->isConfigured()) {
            throw new CourierException(trans('logistics::logistics.errors.not_configured'), 'not_configured');
        }

        try {
            $response = $this->request($timeoutSeconds)->withToken($this->token())->{$method}($this->url($path), $data);
        } catch (ConnectionException $e) {
            throw new CourierException(trans('logistics::logistics.errors.unreachable'), 'unreachable', $e);
        }

        if ($response->status() === 401 && ! $retried) {
            $this->forgetToken();

            return $this->send($method, $path, $data, $timeoutSeconds, true);
        }

        return $this->decode($response);
    }

    /**
     * @throws CourierException
     */
    protected function login(): string
    {
        try {
            $response = $this->request()->post($this->url('/v1/external/auth/login'), [
                'email' => $this->email,
                'password' => $this->password,
            ]);
        } catch (ConnectionException $e) {
            throw new CourierException(trans('logistics::logistics.errors.unreachable'), 'unreachable', $e);
        }

        $token = $response->successful() ? $response->json('token') : null;

        if (! is_string($token) || $token === '') {
            throw new CourierException(trans('logistics::logistics.errors.login_failed'), 'unauthorized');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws CourierException
     */
    protected function decode(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->failed()) {
            $message = $body['message'] ?? null;

            if (is_array($message)) {
                $message = implode(' ', array_map(fn ($part): string => is_array($part) ? implode(' ', $part) : (string) $part, $message));
            }

            throw new CourierException(
                trans('logistics::logistics.errors.provider', ['message' => is_string($message) && $message !== '' ? $message : 'HTTP '.$response->status()]),
                $response->status() === 401 ? 'unauthorized' : 'provider_error',
            );
        }

        return $body;
    }

    protected function request(?float $timeoutSeconds = null): PendingRequest
    {
        $request = Http::integration('shiprocket')->asJson();

        if ($timeoutSeconds !== null) {
            // The storefront estimate cannot wait for retries.
            $request = $request->timeout(max(0.2, $timeoutSeconds))->connectTimeout(max(0.2, $timeoutSeconds))->retry(0, 0, throw: false);
        }

        return $request;
    }

    protected function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    protected function tokenCacheKey(): string
    {
        return 'logistics:shiprocket:token:'.sha1(strtolower(trim((string) $this->email)).'|'.$this->baseUrl);
    }
}
