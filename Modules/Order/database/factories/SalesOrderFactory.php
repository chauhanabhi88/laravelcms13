<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\Payment;

/**
 * A confirmed COD order of ₹499 with a shipping and billing address. Real orders come from
 * PlaceOrderService; this is for screens and status tests.
 *
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_no' => 'ORD-'.now()->format('ym').'-'.fake()->unique()->numerify('9#####'),
            'customer_id' => Customer::factory(),
            'status' => SalesOrder::STATUS_CONFIRMED,
            'payment_method' => Payment::METHOD_COD,
            'payment_status' => Payment::STATUS_PENDING,
            'items_count' => 1,
            'total_mrp' => '999.00',
            'total_discount' => '500.00',
            'coupon_discount' => '0.00',
            'delivery_charge' => '0.00',
            'cod_fee' => '0.00',
            'grand_total' => '499.00',
            'is_reseller_order' => config('core.no'),
            'reseller_margin_total' => '0.00',
            'cod_allowed' => config('core.yes'),
            'channel' => 'web',
            'placed_token' => (string) Str::uuid(),
            'placed_at' => now(),
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }

    public function prepaid(): static
    {
        return $this->state(fn (): array => ['payment_method' => Payment::METHOD_FAKE, 'payment_status' => Payment::STATUS_CAPTURED]);
    }

    public function status(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /**
     * $count seller orders in $status, one ₹499 item each.
     */
    public function withSellerOrders(int $count = 1, string $status = SellerOrder::STATUS_PENDING_ACCEPTANCE): static
    {
        return $this->afterCreating(function (SalesOrder $order) use ($count, $status): void {
            for ($index = 1; $index <= $count; $index++) {
                SellerOrder::factory()->withItems(1)->create([
                    'sales_order_id' => $order->id,
                    'sub_order_no' => $order->order_no.'-'.$index,
                    'status' => $status,
                ]);
            }

            $order->forceFill(['items_count' => $count])->save();
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (SalesOrder $order): void {
            foreach ([SalesOrderAddress::TYPE_SHIPPING, SalesOrderAddress::TYPE_BILLING] as $type) {
                SalesOrderAddress::query()->create([
                    'sales_order_id' => $order->id,
                    'type' => $type,
                    'name' => 'Asha Rao',
                    'phone' => '+919812345678',
                    'address_line1' => '12, Lake View Apartments',
                    'address_line2' => 'MG Road',
                    'pincode' => '560034',
                    'city_name' => 'Bengaluru',
                    'state_name' => 'Karnataka',
                    'is_end_customer' => config('core.no'),
                ]);
            }
        });
    }
}
