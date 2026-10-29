<?php

declare(strict_types=1);

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Promotion\Models\Coupon;
use Modules\Seller\Models\Seller;

/**
 * An enabled, visible, platform-funded flat ₹50 coupon on the whole cart, valid since yesterday
 * for a month, once per customer. States change the shape.
 *
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'T'.Str::upper(Str::random(9)),
            'name' => 'Test coupon',
            'description' => null,
            'type' => Coupon::TYPE_FLAT,
            'value' => '50.00',
            'max_discount' => null,
            'min_cart_value' => '0.00',
            'scope' => Coupon::SCOPE_ALL,
            'scope_ids' => null,
            'funded_by' => Coupon::FUNDED_BY_PLATFORM,
            'seller_id' => null,
            'customer_id' => null,
            'usage_limit_total' => null,
            'usage_limit_per_customer' => 1,
            'first_order_only' => config('core.no'),
            'new_customer_days' => null,
            'channels' => null,
            'payment_methods' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_auto' => config('core.no'),
            'is_visible' => config('core.yes'),
            'stackable' => config('core.no'),
            'status' => config('core.enabled'),
        ];
    }

    public function percent(string $percent, ?string $cap = null): static
    {
        return $this->state(['type' => Coupon::TYPE_PERCENT, 'value' => $percent, 'max_discount' => $cap]);
    }

    public function flat(string $amount): static
    {
        return $this->state(['type' => Coupon::TYPE_FLAT, 'value' => $amount]);
    }

    public function freeDelivery(): static
    {
        return $this->state(['type' => Coupon::TYPE_FREE_DELIVERY, 'value' => '0.00']);
    }

    public function automatic(): static
    {
        return $this->state(['is_auto' => config('core.yes')]);
    }

    public function stackable(): static
    {
        return $this->state(['stackable' => config('core.yes')]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => config('core.disabled')]);
    }

    public function firstOrderOnly(): static
    {
        return $this->state(['first_order_only' => config('core.yes')]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function scoped(string $scope, array $ids): static
    {
        return $this->state(['scope' => $scope, 'scope_ids' => $ids]);
    }

    /**
     * A seller's own coupon on all of their products, funded by them.
     */
    public function forSeller(Seller|int $seller): static
    {
        $sellerId = $seller instanceof Seller ? $seller->id : $seller;

        return $this->state([
            'seller_id' => $sellerId,
            'funded_by' => Coupon::FUNDED_BY_SELLER,
            'scope' => Coupon::SCOPE_SELLER,
            'scope_ids' => [$sellerId],
        ]);
    }

    public function forCustomer(Customer|int $customer): static
    {
        return $this->state(['customer_id' => $customer instanceof Customer ? $customer->id : $customer]);
    }
}
