<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerDeviceToken;

/**
 * Push tokens of customer app installs (customer_device_token). A token belongs to the
 * customer who reported it last; the oldest tokens beyond the cap are dropped.
 */
class DeviceTokenRegistry
{
    public function register(Customer $customer, string $token, ?string $deviceType = null): CustomerDeviceToken
    {
        return DB::transaction(function () use ($customer, $token, $deviceType): CustomerDeviceToken {
            $device = CustomerDeviceToken::query()->updateOrCreate(
                ['device_token' => $token],
                ['customer_id' => $customer->id, 'device_type' => $deviceType],
            );
            $device->forceFill(['last_used_at' => Carbon::now()])->save();

            $keep = CustomerDeviceToken::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('last_used_at')
                ->orderByDesc('id')
                ->limit(max(1, (int) config('notification.max_device_tokens', 10)))
                ->pluck('id');

            CustomerDeviceToken::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('id', $keep)
                ->delete();

            return $device;
        });
    }

    /**
     * @return list<string>
     */
    public function tokensFor(Customer $customer): array
    {
        return CustomerDeviceToken::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('device_token')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->pluck('device_token')
            ->map(fn ($token): string => (string) $token)
            ->values()
            ->all();
    }

    /**
     * Remove tokens the push provider reported as no longer registered.
     *
     * @param  list<string>  $tokens
     */
    public function forget(array $tokens): int
    {
        return $tokens === [] ? 0 : CustomerDeviceToken::query()->whereIn('device_token', $tokens)->delete();
    }

    public function forgetFor(Customer $customer, string $token): int
    {
        return CustomerDeviceToken::query()->where('customer_id', $customer->id)->where('device_token', $token)->delete();
    }
}
