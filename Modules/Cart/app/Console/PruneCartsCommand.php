<?php

declare(strict_types=1);

namespace Modules\Cart\Console;

use Illuminate\Console\Command;
use Modules\Cart\Models\Cart;

/**
 * Deletes expired Buy Now carts and guest carts idle longer than the cookie lifetime
 * (scheduled daily through the Cron module). Customer carts are kept.
 */
class PruneCartsCommand extends Command
{
    protected $signature = 'cart:prune';

    protected $description = 'Delete expired Buy Now carts and abandoned guest carts';

    public function handle(): int
    {
        $buyNow = Cart::query()
            ->buyNow()
            ->where('last_activity_at', '<', now()->subMinutes((int) config('cart.buy_now_ttl_minutes', 60)))
            ->delete();

        $guests = Cart::query()
            ->whereNull('customer_id')
            ->where(fn ($query) => $query
                ->where('last_activity_at', '<', now()->subDays((int) config('cart.cookie_days', 30)))
                ->orWhereNull('last_activity_at'))
            ->delete();

        $this->info("Deleted {$buyNow} Buy Now and {$guests} guest carts.");

        return self::SUCCESS;
    }
}
