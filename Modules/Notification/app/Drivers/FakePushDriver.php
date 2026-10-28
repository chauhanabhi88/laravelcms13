<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers;

use Modules\Notification\Contracts\PushDriver;

/**
 * Test driver: keeps pushes in memory. Tokens listed in $unregistered come back invalid.
 *
 *     $push = new FakePushDriver;
 *     $this->app->instance(PushDriver::class, $push);
 */
class FakePushDriver implements PushDriver
{
    /**
     * @var list<array{tokens: list<string>, message: PushMessage}>
     */
    public array $sent = [];

    /**
     * @param  list<string>  $unregistered
     */
    public function __construct(public array $unregistered = []) {}

    public function send(array $tokens, PushMessage $message): PushResult
    {
        $valid = array_values(array_diff($tokens, $this->unregistered));
        $this->sent[] = ['tokens' => $valid, 'message' => $message];

        return new PushResult(
            count($valid),
            array_map(fn (string $token): string => 'fake-'.substr($token, -6), $valid),
            array_values(array_intersect($tokens, $this->unregistered)),
        );
    }
}
