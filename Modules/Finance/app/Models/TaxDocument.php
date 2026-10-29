<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Models\Seller;

/**
 * A GST document (FR-FIN-006): the seller's tax invoice to the buyer, a credit note for a
 * refunded return, or the platform's monthly fee invoice to the seller. Issued by
 * TaxDocumentService; the PDF is rendered by the GenerateTaxDocumentPdf job.
 *
 * @property int $id
 * @property string $type
 * @property string $number
 * @property string|null $reference_number
 * @property int $seller_id
 * @property int|null $seller_order_id
 * @property int|null $return_request_id
 * @property string|null $period
 * @property Carbon $document_date
 * @property Money $taxable_value
 * @property Money $cgst
 * @property Money $sgst
 * @property Money $igst
 * @property Money $total
 * @property list<array<string, mixed>>|null $lines
 * @property string|null $path
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TaxDocument extends MarketplaceModel
{
    public const TYPE_TAX_INVOICE = 'tax_invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_FEE_INVOICE = 'fee_invoice';

    public const TYPES = [self::TYPE_TAX_INVOICE, self::TYPE_CREDIT_NOTE, self::TYPE_FEE_INVOICE];

    protected $table = 'tax_document';

    protected $fillable = [
        'type',
        'number',
        'reference_number',
        'seller_id',
        'seller_order_id',
        'return_request_id',
        'period',
        'document_date',
        'taxable_value',
        'cgst',
        'sgst',
        'igst',
        'total',
        'lines',
        'path',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'seller_order_id' => 'integer',
            'return_request_id' => 'integer',
            'document_date' => 'date',
            'taxable_value' => MoneyCast::class,
            'cgst' => MoneyCast::class,
            'sgst' => MoneyCast::class,
            'igst' => MoneyCast::class,
            'total' => MoneyCast::class,
            'lines' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @param  Builder<TaxDocument>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    public function isGenerated(): bool
    {
        return $this->path !== null && $this->generated_at !== null;
    }

    public function taxTotal(): Money
    {
        return $this->cgst->plus($this->sgst)->plus($this->igst);
    }
}
