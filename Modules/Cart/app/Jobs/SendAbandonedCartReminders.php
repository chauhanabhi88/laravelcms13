<?php

declare(strict_types=1);

namespace Modules\Cart\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Cart\Models\Cart;
use Modules\Cart\Support\CartSettings;
use Modules\Notification\Services\NotificationHub;
use Throwable;

/**
 * Abandoned-cart reminder (FR-CRT-007, "C" priority), run hourly by the Cron module: customer
 * carts with items, idle between 3 and 24 hours and not reminded since their last change,
 * get the "cart.abandoned" notification. It is a promotional event, so the hub drops it for
 * customers who switched promotional push off. Off unless Settings > Cart enables it.
 */
class SendAbandonedCartReminders implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 3600;

    public function handle(NotificationHub $hub): int
    {
        if (! CartSettings::abandonedCartEnabled()) {
            return 0;
        }

        $sent = 0;
        $now = now();

        Cart::query()
            ->standard()
            ->whereNotNull('customer_id')
            ->whereNull('reminded_at')
            ->whereBetween('last_activity_at', [
                $now->copy()->subHours((int) config('cart.abandoned.within_hours', 24)),
                $now->copy()->subHours((int) config('cart.abandoned.after_hours', 3)),
            ])
            ->whereHas('items', fn ($query) => $query->active())
            ->with('customer')
            ->withSum(['items as active_quantity' => fn ($query) => $query->active()], 'quantity')
            ->chunkById(200, function (Collection $carts) use ($hub, &$sent): void {
                foreach ($carts as $cart) {
                    /** @var Cart $cart */
                    if ($cart->customer === null) {
                        continue;
                    }

                    try {
                        $hub->event('cart.abandoned', $cart->customer, [
                            'item_count' => (int) $cart->getAttribute('active_quantity'),
                            'url' => frontRoute('cart.index'),
                        ]);
                        $sent++;
                    } catch (Throwable $e) {
                        report($e);
                    }

                    // Marked even when skipped, so one cart is never retried every hour.
                    Cart::query()->whereKey($cart->id)->update(['reminded_at' => now()]);
                }
            });

        return $sent;
    }
}
