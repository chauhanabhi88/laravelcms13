<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers\Fcm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Notification\Contracts\PushDriver;
use Modules\Notification\Drivers\PushMessage;
use Modules\Notification\Drivers\PushResult;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 (one request per device token). Tokens FCM reports as
 * UNREGISTERED are returned as invalid so the channel deletes them.
 */
class FcmPushDriver implements PushDriver
{
    /**
     * @param  array{project_id?: string|null, send_endpoint?: string}  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly FcmAccessToken $accessToken,
    ) {}

    public function send(array $tokens, PushMessage $message): PushResult
    {
        $endpoint = $this->endpoint();
        $messageIds = [];
        $invalid = [];
        $errors = [];

        foreach ($tokens as $token) {
            try {
                $response = $this->post($endpoint, $token, $message);

                if ($response->status() === 401) {
                    $this->accessToken->forget();
                    $response = $this->post($endpoint, $token, $message);
                }
            } catch (ConnectionException $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            if ($response->successful()) {
                $messageIds[] = (string) $response->json('name');

                continue;
            }

            if ($this->isUnregistered($response)) {
                $invalid[] = $token;
            }

            $errors[] = (string) ($response->json('error.message') ?? 'FCM responded with HTTP '.$response->status());
        }

        return new PushResult(count($messageIds), $messageIds, $invalid, array_values(array_unique($errors)));
    }

    protected function post(string $endpoint, string $token, PushMessage $message): Response
    {
        return Http::integration('fcm')
            ->withToken($this->accessToken->get())
            ->post($endpoint, ['message' => array_filter([
                'token' => $token,
                'notification' => ['title' => $message->title, 'body' => $message->body],
                'data' => $message->data === [] ? null : array_map('strval', $message->data),
            ])]);
    }

    private function isUnregistered(Response $response): bool
    {
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return $response->status() === 404 && $response->json('error.status') === 'NOT_FOUND';
    }

    private function endpoint(): string
    {
        $project = $this->config['project_id'] ?? null;
        $project = filled($project) ? (string) $project : $this->accessToken->projectId();

        if (! filled($project)) {
            throw new RuntimeException('FCM_PROJECT_ID is not set.');
        }

        return str_replace('{project}', (string) $project, (string) ($this->config['send_endpoint'] ?? 'https://fcm.googleapis.com/v1/projects/{project}/messages:send'));
    }
}
