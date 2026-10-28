<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Contracts\SmsDriver;
use Modules\Customer\Support\PhoneNumber;
use Modules\Notification\Contracts\PushDriver;
use Modules\Notification\Contracts\WhatsAppDriver;
use Modules\Notification\Drivers\PushMessage;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Support\Mask;
use Modules\Notification\Support\TemplateRenderer;

/**
 * "Test send" from the admin template screen: renders the template with sample values
 * ("[order_no]") and sends it straight through the driver to a phone number or push token.
 * Each test is written to the delivery log without a recipient.
 */
class TemplateTestSender
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * @throws InvalidArgumentException for a channel that cannot be tested or a bad destination
     */
    public function send(NotificationTemplate $template, string $destination): NotificationLog
    {
        $vars = $this->sampleVars($template);
        $title = $this->renderer->renderText($template->title ?: '{{app_name}}', $vars);
        $body = $this->renderer->renderText($template->body, $vars);

        [$masked, $successful, $messageId, $error] = match ($template->channel) {
            'sms' => $this->sms($template, $destination, $body, $vars),
            'whatsapp' => $this->whatsApp($template, $destination, $body, $vars),
            'push' => $this->push($template, $destination, $title, $body),
            default => throw new InvalidArgumentException(trans('notification::notification_template.messages.test_unsupported')),
        };

        return NotificationLog::query()->create([
            'notification_id' => (string) Str::uuid(),
            'event_key' => $template->event_key,
            'channel' => $template->channel,
            'destination' => $masked,
            'status' => $successful ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
            'provider_message_id' => $messageId,
            'error' => $error,
            'payload' => ['test' => true, 'template_id' => $template->id, 'data' => $vars],
            'sent_at' => $successful ? now() : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function sampleVars(NotificationTemplate $template): array
    {
        $vars = ['app_name' => (string) config('app.name')];

        foreach ($this->renderer->placeholders($template->title.' '.$template->body) as $placeholder) {
            $vars[$placeholder] ??= '['.$placeholder.']';
        }

        return $vars;
    }

    /**
     * @param  array<string, string>  $vars
     * @return array{0: string, 1: bool, 2: string|null, 3: string|null}
     */
    private function sms(NotificationTemplate $template, string $destination, string $body, array $vars): array
    {
        $phone = $this->phone($destination);
        $result = app(SmsDriver::class)->send(
            $phone,
            $template->provider_template_id ?: $template->event_key,
            array_intersect_key($vars, array_flip($this->renderer->placeholders($template->body))) + [
                'message' => $this->renderer->limitSms($body, (int) config('notification.sms_max_segments', 2)),
            ],
        );

        return [Mask::phone($phone), $result->successful, $result->messageId, $result->error];
    }

    /**
     * @param  array<string, string>  $vars
     * @return array{0: string, 1: bool, 2: string|null, 3: string|null}
     */
    private function whatsApp(NotificationTemplate $template, string $destination, string $body, array $vars): array
    {
        $phone = $this->phone($destination);
        $result = app(WhatsAppDriver::class)->send(
            $phone,
            $template->provider_template_id,
            array_intersect_key($vars, array_flip($this->renderer->placeholders($template->body))),
            $body,
        );

        return [Mask::phone($phone), $result->successful, $result->messageId, $result->error];
    }

    /**
     * @return array{0: string, 1: bool, 2: string|null, 3: string|null}
     */
    private function push(NotificationTemplate $template, string $destination, string $title, string $body): array
    {
        $token = trim($destination);

        if (strlen($token) < 20) {
            throw new InvalidArgumentException(trans('notification::notification_template.messages.test_bad_token'));
        }

        $result = app(PushDriver::class)->send([$token], new PushMessage($title, $body, ['event_key' => $template->event_key, 'test' => '1']));

        return [Mask::token($token), $result->successful(), $result->messageIds[0] ?? null, $result->errors === [] ? null : implode('; ', $result->errors)];
    }

    private function phone(string $destination): string
    {
        $phone = PhoneNumber::normalise($destination);

        if ($phone === null) {
            throw new InvalidArgumentException(trans('notification::notification_template.messages.test_bad_phone'));
        }

        return $phone;
    }
}
