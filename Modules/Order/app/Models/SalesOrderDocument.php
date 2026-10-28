<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A document of a seller order (plan M14 §3.3): our PDFs on the private disk (path), or a
 * courier's document by URL. M17 reuses the table for tax invoices and credit notes.
 *
 * @property int $id
 * @property int $seller_order_id
 * @property int|null $label_batch_id
 * @property string $type
 * @property string|null $path
 * @property string|null $url
 * @property string|null $number
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SalesOrderDocument extends MarketplaceModel
{
    public const TYPE_LABEL = 'label';

    public const TYPE_PACKING_SLIP = 'packing_slip';

    public const TYPE_TAX_INVOICE = 'tax_invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_MANIFEST = 'manifest';

    public const TYPES = [self::TYPE_LABEL, self::TYPE_PACKING_SLIP, self::TYPE_TAX_INVOICE, self::TYPE_CREDIT_NOTE, self::TYPE_MANIFEST];

    protected $table = 'sales_order_document';

    protected $fillable = ['seller_order_id', 'label_batch_id', 'type', 'path', 'url', 'number', 'generated_at'];

    protected $hidden = ['path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_order_id' => 'integer',
            'label_batch_id' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<LabelBatch, $this>
     */
    public function labelBatch(): BelongsTo
    {
        return $this->belongsTo(LabelBatch::class, 'label_batch_id');
    }
}
