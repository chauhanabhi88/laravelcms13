<?php

declare(strict_types=1);

namespace Modules\Seller\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Database\Factories\SellerKycDocumentFactory;

/**
 * A KYC upload on the private disk. Never linked directly: sellers and admins download
 * it through an authorised controller.
 *
 * @property int $id
 * @property int $seller_id
 * @property string $type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property string $status
 * @property string|null $review_note
 * @property-read Seller $seller
 */
class SellerKycDocument extends MarketplaceModel
{
    /** @use HasFactory<SellerKycDocumentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'seller_kyc_document';

    protected $fillable = [
        'seller_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'status',
        'review_note',
    ];

    protected $hidden = ['disk', 'path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function typeLabel(): string
    {
        return trans('seller::seller.kyc_types.'.$this->type);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    protected static function newFactory(): SellerKycDocumentFactory
    {
        return SellerKycDocumentFactory::new();
    }
}
