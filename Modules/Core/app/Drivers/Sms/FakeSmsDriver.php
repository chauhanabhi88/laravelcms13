<?php

declare(strict_types=1);

namespace Modules\Core\Drivers\Sms;

use Modules\Core\Contracts\SmsDriver;
use PHPUnit\Framework\Assert;

/**
 * Test driver: keeps messages in memory for assertions.
 *
 *     $sms = new FakeSmsDriver;
 *     $this->app->instance(SmsDriver::class, $sms);
 */
class FakeSmsDriver implements SmsDriver
{
    /**
     * @var list<array{to: string, template: string, vars: array<string, string|int>}>
     */
    private array $messages = [];

    private bool $failing = false;

    public function send(string $to, string $templateKey, array $vars = []): SmsResult
    {
        if ($this->failing) {
            return SmsResult::failed('Fake SMS gateway failure.');
        }

        $this->messages[] = ['to' => $to, 'template' => $templateKey, 'vars' => $vars];

        return SmsResult::sent('fake-'.count($this->messages));
    }

    /**
     * Make every following send fail, to exercise error paths.
     */
    public function failing(bool $failing = true): static
    {
        $this->failing = $failing;

        return $this;
    }

    /**
     * @return list<array{to: string, template: string, vars: array<string, string|int>}>
     */
    public function sent(?string $to = null): array
    {
        return array_values(array_filter($this->messages, fn (array $message): bool => $to === null || $message['to'] === $to));
    }

    /**
     * The OTP of the most recent message to a number.
     */
    public function lastOtpFor(string $to): ?string
    {
        $messages = $this->sent($to);
        $last = end($messages);

        return $last === false ? null : (isset($last['vars']['otp']) ? (string) $last['vars']['otp'] : null);
    }

    public function assertSentTo(string $to, int $times = 1): void
    {
        Assert::assertCount($times, $this->sent($to), "Expected {$times} SMS to [{$to}].");
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->messages, 'Expected no SMS to be sent.');
    }
}
