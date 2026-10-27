<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Closure;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Registers Http::integration('<name>'): the one way to call third-party APIs.
 *
 * Timeouts and retries come from config('core.integrations'). Retries only happen on
 * connection errors, 429 and 5xx, never on other 4xx. Every exchange is logged to the
 * "integrations" channel with credentials and secret-looking fields redacted.
 */
class IntegrationHttp
{
    private const REDACTED = '[REDACTED]';

    public static function register(): void
    {
        Http::macro('integration', function (string $name): PendingRequest {
            $config = (array) config('core.integrations');

            return Http::timeout((int) ($config['timeout'] ?? 10))
                ->connectTimeout((int) ($config['connect_timeout'] ?? 5))
                ->retry(
                    (int) ($config['retries'] ?? 3),
                    (int) ($config['retry_delay_ms'] ?? 200),
                    fn (Throwable $exception): bool => IntegrationHttp::shouldRetry($exception),
                    throw: false,
                )
                ->acceptJson()
                ->withUserAgent(Str::slug((string) config('app.name')).'-integration')
                ->withMiddleware(IntegrationHttp::logger($name));
        });
    }

    public static function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->serverError() || $exception->response->status() === 429);
    }

    /**
     * Guzzle middleware logging one line per attempt.
     */
    public static function logger(string $name): Closure
    {
        return fn (callable $handler): Closure => function (RequestInterface $request, array $options) use ($handler, $name) {
            $startedAt = microtime(true);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $name, $startedAt): ResponseInterface {
                    $level = $response->getStatusCode() >= 400 ? 'warning' : 'info';
                    Log::channel('integrations')->{$level}("{$name} {$request->getMethod()} {$response->getStatusCode()}", static::context($request, $response, $startedAt));

                    return $response;
                },
                function ($reason) use ($request, $name, $startedAt) {
                    Log::channel('integrations')->error("{$name} {$request->getMethod()} failed", static::context($request, null, $startedAt) + [
                        'error' => $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
                    ]);

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function context(RequestInterface $request, ?ResponseInterface $response, float $startedAt): array
    {
        $context = [
            'url' => static::redactUrl((string) $request->getUri()),
            'status' => $response?->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];

        if (config('core.integrations.log_bodies', true)) {
            $context['request'] = static::body($request);
            $context['response'] = $response === null ? null : static::body($response);
        }

        return $context;
    }

    protected static function body(MessageInterface $message): mixed
    {
        $stream = $message->getBody();

        if (! $stream->isSeekable()) {
            return '[stream]';
        }

        $contents = (string) $stream;
        $stream->rewind();

        if ($contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (is_array($decoded)) {
            return static::redact($decoded);
        }

        parse_str($contents, $form);
        if (str_contains($message->getHeaderLine('Content-Type'), 'form-urlencoded') && $form !== []) {
            return static::redact($form);
        }

        return Str::limit($contents, (int) config('core.integrations.log_body_limit', 2000));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && static::isSecretKey($key)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = static::redact($value);
            }
        }

        return $data;
    }

    public static function redactUrl(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $params);

        return Str::before($url, '?').'?'.http_build_query(static::redact($params));
    }

    /**
     * "token", "access_token", "x-api-key" and "password_confirmation" are secret; "company" is not.
     */
    public static function isSecretKey(string $key): bool
    {
        $key = str_replace('-', '_', strtolower($key));

        foreach ((array) config('core.integrations.redact_keys', []) as $secret) {
            if ($key === $secret || str_ends_with($key, '_'.$secret) || str_starts_with($key, $secret.'_')) {
                return true;
            }
        }

        return false;
    }
}
