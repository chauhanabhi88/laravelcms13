<?php

declare(strict_types=1);

namespace Modules\Core\Services\Otp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Modules\Core\Contracts\SmsDriver;
use Modules\Core\Models\OtpCode;
use Throwable;

/**
 * Shared one-time-password flow for customer (M08) and seller (M03) authentication.
 *
 * Rules (config('core.otp')): numeric codes stored as hashes only, a short expiry,
 * a resend cooldown, hourly send caps per identifier and per IP, and a verify-attempt
 * cap after which the code is dead. A new send retires earlier codes for the same
 * identifier and purpose.
 */
class OtpService
{
    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public function __construct(private readonly SmsDriver $sms) {}

    public function send(string $channel, string $identifier, string $purpose, ?string $ip = null): OtpSendResult
    {
        $identifier = static::normaliseIdentifier($channel, $identifier);
        $config = (array) config('core.otp');
        $now = Carbon::now();

        $latest = OtpCode::query()->for($identifier, $purpose)->latest('id')->first(['id', 'created_at']);
        $cooldown = (int) $config['resend_cooldown_seconds'];

        if ($latest !== null && $latest->created_at->gt($now->copy()->subSeconds($cooldown))) {
            return new OtpSendResult(OtpSendStatus::Cooldown, max(1, $cooldown - (int) $latest->created_at->diffInSeconds($now)), $identifier);
        }

        $hourAgo = $now->copy()->subHour();
        $sentToIdentifier = OtpCode::query()->where('identifier', $identifier)->where('created_at', '>=', $hourAgo)->count();
        $sentFromIp = $ip === null ? 0 : OtpCode::query()->where('ip', $ip)->where('created_at', '>=', $hourAgo)->count();

        if ($sentToIdentifier >= (int) $config['max_sends_per_identifier_per_hour'] || $sentFromIp >= (int) $config['max_sends_per_ip_per_hour']) {
            return new OtpSendResult(OtpSendStatus::Throttled, $this->secondsUntilWindowFrees($identifier, $ip, $hourAgo), $identifier);
        }

        $code = $this->generateCode((int) $config['length']);

        OtpCode::query()->for($identifier, $purpose)->usable()->update(['expires_at' => $now]);

        $otp = OtpCode::query()->create([
            'channel' => $channel,
            'identifier' => $identifier,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => $now->copy()->addMinutes((int) $config['expires_minutes']),
            'ip' => $ip,
        ]);

        if (! $this->deliver($channel, $identifier, $code, (int) $config['expires_minutes'])) {
            $otp->update(['expires_at' => $now]);

            return new OtpSendResult(OtpSendStatus::Failed, 0, $identifier);
        }

        return new OtpSendResult(OtpSendStatus::Sent, 0, $identifier);
    }

    /**
     * Check a code. A correct code is consumed and cannot be reused; every call counts as an attempt.
     */
    public function verify(string $identifier, string $purpose, string $code): bool
    {
        try {
            $identifier = static::normaliseIdentifier(str_contains($identifier, '@') ? self::CHANNEL_EMAIL : self::CHANNEL_SMS, $identifier);
        } catch (InvalidArgumentException) {
            return false;
        }

        $maxAttempts = (int) config('core.otp.max_attempts');

        return DB::transaction(function () use ($identifier, $purpose, $code, $maxAttempts): bool {
            $otp = OtpCode::query()->for($identifier, $purpose)->usable()->latest('id')->lockForUpdate()->first();

            if ($otp === null || $otp->attempts >= $maxAttempts) {
                return false;
            }

            $otp->attempts++;
            $matches = preg_match('/^\d+$/', $code) === 1 && Hash::check($code, $otp->code_hash);

            if ($matches) {
                $otp->consumed_at = Carbon::now();
            } elseif ($otp->attempts >= $maxAttempts) {
                $otp->expires_at = Carbon::now();
            }

            $otp->save();

            return $matches;
        });
    }

    /**
     * E.164 for phones (Indian 10-digit numbers get +91), lower case for emails.
     */
    public static function normaliseIdentifier(string $channel, string $identifier): string
    {
        $identifier = trim($identifier);

        if ($channel === self::CHANNEL_EMAIL) {
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Invalid email address for OTP.');
            }

            return strtolower($identifier);
        }

        if ($channel !== self::CHANNEL_SMS) {
            throw new InvalidArgumentException("Unsupported OTP channel [{$channel}].");
        }

        $hasPlus = str_starts_with($identifier, '+');
        $digits = preg_replace('/\D+/', '', $identifier);

        $e164 = match (true) {
            ! $hasPlus && strlen($digits) === 10 => '+91'.$digits,
            ! $hasPlus && strlen($digits) === 11 && $digits[0] === '0' => '+91'.substr($digits, 1),
            ! $hasPlus && strlen($digits) === 12 && str_starts_with($digits, '91') => '+'.$digits,
            $hasPlus => '+'.$digits,
            default => null,
        };

        if ($e164 === null || ! preg_match('/^\+[1-9]\d{7,14}$/', $e164) || (str_starts_with($e164, '+91') && ! preg_match('/^\+91[6-9]\d{9}$/', $e164))) {
            throw new InvalidArgumentException('Invalid mobile number for OTP.');
        }

        return $e164;
    }

    private function generateCode(int $length): string
    {
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function deliver(string $channel, string $identifier, string $code, int $minutes): bool
    {
        if ($channel === self::CHANNEL_SMS) {
            return $this->sms->send($identifier, 'otp', ['otp' => $code, 'minutes' => $minutes])->successful;
        }

        try {
            Mail::raw(trans('core::otp.email.body', ['code' => $code, 'minutes' => $minutes]), function ($message) use ($identifier): void {
                $message->to($identifier)->subject(trans('core::otp.email.subject', ['app' => config('app.name')]));
            });
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * When the oldest send inside the hourly window drops out of it.
     */
    private function secondsUntilWindowFrees(string $identifier, ?string $ip, Carbon $hourAgo): int
    {
        $oldest = OtpCode::query()
            ->where('created_at', '>=', $hourAgo)
            ->where(fn ($query) => $query->where('identifier', $identifier)->when($ip !== null, fn ($query) => $query->orWhere('ip', $ip)))
            ->min('created_at');

        return $oldest === null ? 3600 : max(1, (int) Carbon::now()->diffInSeconds(Carbon::parse($oldest)->addHour()));
    }
}
