<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Customer\Models\Customer;
use Modules\Logistics\Models\Courier;
use Modules\Order\DTO\CheckoutSession;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\ProductVariant;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewService;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

require_once __DIR__.'/../../Order/tests/FulfilmentHelpers.php';

/*
| Shared helpers for the Review module tests (require_once'd: Pest test files with same-named
| global functions would collide). Orders go through the real checkout, fake courier and the
| order desk until delivered; review photos land on a fake public disk.
*/

if (! function_exists('reviewSetup')) {
    function reviewSetup(): Courier
    {
        $courier = fulfilmentSetup();
        Storage::fake((string) config('review.uploads.disk', 'public'));
        config(['notification.quiet_hours' => '']);
        reviewSettings([
            'auto_publish' => 1,
            'min_body_length' => 10,
            'max_images' => 5,
            'edit_window_days' => 30,
            'report_threshold' => 3,
            'prompt_delay_days' => 3,
        ]);

        return $courier;
    }

    /**
     * Merge values into Settings > Reviews.
     *
     * @param  array<string, mixed>  $values
     */
    function reviewSettings(array $values): void
    {
        $current = json_decode((string) DB::table('settings')->where('name', 'review')->value('value'), true) ?: [];
        DB::table('settings')->updateOrInsert(
            ['name' => 'review'],
            ['value' => json_encode(array_merge($current, $values)), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * A delivered order of one seller (qty × the variant): [order, seller order, order item,
     * customer, variant].
     *
     * @param  array<string, mixed>  $variant
     * @param  array<string, mixed>  $product
     * @return array{0: SalesOrder, 1: SellerOrder, 2: SalesOrderItem, 3: Customer, 4: ProductVariant}
     */
    function reviewDeliveredOrder(array $variant = [], ?Customer $customer = null, ?Seller $seller = null, array $product = [], int $quantity = 1): array
    {
        $seller ??= orderSeller();
        $item = cartVariant(array_merge(['stock' => 10, 'price' => '499', 'weight_grams' => 400], $variant), $seller, $product);
        $customer ??= orderCustomer();
        orderCart($customer, [[$item, $quantity]]);

        $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_COD));
        $sellerOrder = SellerOrder::query()->where('sales_order_id', $result->order->id)->firstOrFail();
        fulfilment()->accept($sellerOrder);
        fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered']);

        return [
            $result->order->refresh(),
            $sellerOrder->refresh(),
            SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail(),
            $customer,
            $item,
        ];
    }

    /**
     * A confirmed order still waiting for the seller: [order, seller order, order item, customer].
     *
     * @return array{0: SalesOrder, 1: SellerOrder, 2: SalesOrderItem, 3: Customer}
     */
    function reviewUndeliveredOrder(): array
    {
        [$order, $sellerOrder] = pendingFulfilmentOrder();
        $order->load('customer');

        return [$order, $sellerOrder, SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail(), $order->customer];
    }

    /**
     * A JPEG upload that decodes (re-encoding needs a real image).
     */
    function reviewPhoto(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 900, 700);
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    function writeReview(Customer $customer, SalesOrderItem $item, int $rating = 5, ?string $body = 'Lovely fabric and a perfect fit.', array $photos = [], ?string $title = null): ProductReview
    {
        return app(ReviewService::class)->create($customer, $item->fresh(['salesOrder', 'sellerOrder']), new ReviewSubmission($rating, $title, $body, $photos));
    }

    /**
     * A published review of another delivered order of the same product (same seller),
     * by a new customer.
     */
    function reviewByAnotherBuyer(ProductVariant $variant, int $rating = 4, ?string $body = 'Good value for the price.', array $photos = []): ProductReview
    {
        $seller = Seller::query()->findOrFail($variant->seller_id);
        $customer = orderCustomer();
        orderCart($customer, [[$variant->fresh(), 1]]);
        $result = placeOrder($customer, orderCheckout($customer, CheckoutSession::METHOD_COD));
        $sellerOrder = SellerOrder::query()->where('sales_order_id', $result->order->id)->where('seller_id', $seller->id)->firstOrFail();
        fulfilment()->accept($sellerOrder);
        fulfilmentScans($sellerOrder, ['picked_up', 'in_transit', 'out_for_delivery', 'delivered']);

        return writeReview($customer, SalesOrderItem::query()->where('seller_order_id', $sellerOrder->id)->firstOrFail(), $rating, $body, $photos);
    }

    /**
     * An admin whose role holds the given permissions (default: every Review permission).
     *
     * @param  list<string>|null  $permissions
     */
    function reviewAdmin(?array $permissions = null): User
    {
        $permissions ??= array_keys(array_merge(...array_values(require base_path('Modules/Review/config/permissions.php'))));

        $roleId = DB::table('role')->insertGetId([
            'name' => 'Moderator',
            'slug' => 'review_moderator_'.uniqid(),
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->forceCreate([
            'name' => 'Review Moderator',
            'email' => uniqid('review-admin-').'@example.test',
            'password' => bcrypt('Secret#123'),
            'role_id' => $roleId,
            'status' => 1,
        ]);
    }
}
