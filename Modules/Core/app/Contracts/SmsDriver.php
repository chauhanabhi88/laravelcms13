<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Drivers\Sms\SmsResult;

/**
 * An SMS gateway. Bound from config('core.sms.driver'): log, fake or msg91.
 */
interface SmsDriver
{
    /**
     * Send a templated message.
     *
     * @param  string  $to  E.164 phone number, e.g. +919876543210
     * @param  string  $templateKey  key under config('core.sms.<driver>.templates'), e.g. "otp", or a
     *                               provider (DLT) template id sent by the notification hub
     * @param  array<string, string|int>  $vars  template variables, e.g. ['otp' => '123456']
     */
    public function send(string $to, string $templateKey, array $vars = []): SmsResult;
}
