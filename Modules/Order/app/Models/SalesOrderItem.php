<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Order\Database\Factories\SalesOrderItemFactory;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * One ordered variant with its placement snapshots (price, MRP, tax split, commission,
 * reseller price, return policy). Money columns are per line unless noted "unit".
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int $seller_order_id
 * @property int $product_id
 * @property int $variant_id
 * @property string $sku
 * @property string|null $seller_sku
 * @property string $name
 * @property string|null $variant_label
 * @property string|null $image
 * @property string|null $hsn_code
 * @property int $quantity
 * @property Money $mrp
 * @property Money $price
 * @property Money $discount_allocated
 * @property string|null $discount_funded_by platform | seller | mixed (M18)
 * @property Money $seller_discount the seller-funded part of discount_allocated
 * @property int|null $flash_deal_item_id sold at this flash-deal price (M18)
 * @property Money $taxable_value
 * @property string $gst_percent
 * @property Money $cgst
 * @property Money $sgst
 * @property Money $igst
 * @property Money $commission_amount
 * @property int|null $commission_rule_id
 * @property Money|null $customer_price
 * @property Money $reseller_margin
 * @property int $return_window_days
 * @property string|null $return_type
 * @property string $status
 * @property int $cancelled_qty
 * @property int $returned_qty
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SalesOrderItem extends MarketplaceModel
{
    /** @use HasFactory<SalesOrderItemFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RETURN_REQUESTED = 'return_requested';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_EXCHANGED = 'exchanged';

    /**
     * Who pays for the item's coupon share (M18): the platform keeps crediting the seller at the
     * full price; a seller-funded share lowers the seller's sale credit.
     */
    public const FUNDED_BY_PLATFORM = 'platform';

    public const FUNDED_BY_SELLER = 'seller';

    public const FUNDED_BY_MIXED = 'mixed';

    protected $table = 'sales_order_item';

    protected $fillable = [
        'sales_order_id',
        'seller_order_id',
        'product_id',
        'variant_id',
        'sku',
        'seller_sku',
        'name',
        'variant_label',
        'image',
        'hsn_code',
        'quantity',
        'mrp',
        'price',
        'discount_allocated',
        'discount_funded_by',
        'seller_discount',
        'flash_deal_item_id',
        'taxable_value',
        'gst_percent',
        'cgst',
        'sgst',
        'igst',
        'commission_amount',
        'commission_rule_id',
        'customer_price',
        'reseller_margin',
        'return_window_days',
        'return_type',
        'status',
        'cancelled_qty',
        'returned_qty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'seller_order_id' => 'integer',
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'mrp' => MoneyCast::class,
            'price' => MoneyCast::class,
            'discount_allocated' => MoneyCast::class,
            'seller_discount' => MoneyCast::class,
            'flash_deal_item_id' => 'integer',
            'taxable_value' => MoneyCast::class,
            'cgst' => MoneyCast::class,
            'sgst' => MoneyCast::class,
            'igst' => MoneyCast::class,
            'commission_amount' => MoneyCast::class,
            'commission_rule_id' => 'integer',
            'customer_price' => MoneyCast::class,
            'reseller_margin' => MoneyCast::class,
            'return_window_days' => 'integer',
            'cancelled_qty' => 'integer',
            'returned_qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Price × qty (GST inclusive, before the coupon share).
     */
    public function lineTotal(): Money
    {
        return $this->price->times($this->quantity);
    }

    /**
     * What the buyer paid for the line: price × qty minus the coupon share.
     */
    public function netLineTotal(): Money
    {
        return $this->lineTotal()->minus($this->discount_allocated);
    }

    public function taxAmount(): Money
    {
        return $this->cgst->plus($this->sgst)->plus($this->igst);
    }

    /**
     * (customer price − price) × qty: what a reseller earns if their customer pays on delivery.
     */
    public function potentialMargin(): Money
    {
        if ($this->customer_price === null || $this->customer_price->compare($this->price) <= 0) {
            return Money::zero();
        }

        return $this->customer_price->minus($this->price)->times($this->quantity);
    }

    protected static function newFactory(): SalesOrderItemFactory
    {
        return SalesOrderItemFactory::new();
    }
}
