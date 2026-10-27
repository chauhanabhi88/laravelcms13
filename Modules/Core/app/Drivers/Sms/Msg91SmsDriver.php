<?php

declare(strict_types=1);

namespace Modules\Core\Drivers\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\SmsDriver;

/**
 * MSG91 Flow API. Each template key maps to a DLT-registered template id in
 * config('core.sms.msg91.templates'); $vars are passed as the template's variables.
 */
class Msg91SmsDriver implements SmsDriver
{
    /**
     * @param  array{endpoint?: string, auth_key?: string|null, sender_id?: string|null, templates?: array<string, string|null>}  $config
     */
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $templateKey, array $vars = []): SmsResult
    {
        $templateId = $this->config['templates'][$templateKey] ?? null;

        if (empty($this->config['auth_key']) || empty($templateId)) {
            return SmsResult::failed("MSG91 is not configured for the [{$templateKey}] template.");
        }

        $payload = array_filter([
            'template_id' => $templateId,
            'sender' => $this->config['sender_id'] ?? null,
            'short_url' => '0',
            'recipients' => [['mobiles' => ltrim($to, '+')] + $vars],
        ], fn ($value): bool => $value !== null && $value !== '');

        try {
            $response = Http::integration('msg91')
                ->withHeaders(['authkey' => $this->config['auth_key']])
                ->post($this->config['endpoint'] ?? 'https://control.msg91.com/api/v5/flow', $payload);
        } catch (ConnectionException $e) {
            return SmsResult::failed($e->getMessage());
        }

        if ($response->successful() && $response->json('type') === 'success') {
            return SmsResult::sent((string) $response->json('message'));
        }

        return SmsResult::failed((string) ($response->json('message') ?? 'MSG91 responded with HTTP '.$response->status()));
    }
}
