<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Product\Database\Factories\CatalogUploadFactory;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

/**
 * A supplier's bulk upload: a new catalog (category template) or a price & stock sheet.
 * The file lives on the private disk; ProcessCatalogUpload / ProcessPriceStockUpload fill
 * the counters, the row errors and the error report.
 *
 * @property int $id
 * @property int $seller_id
 * @property int|null $seller_user_id
 * @property string $type
 * @property int|null $catalog_category_id
 * @property int $save_as_draft
 * @property string $file_path
 * @property string $original_name
 * @property string $status
 * @property int $total_rows
 * @property int $success_rows
 * @property int $failed_rows
 * @property string|null $error_report_path
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class CatalogUpload extends MarketplaceModel
{
    /** @use HasFactory<CatalogUploadFactory> */
    use HasFactory;

    public const TYPE_NEW_CATALOG = 'new_catalog';

    public const TYPE_PRICE_STOCK = 'price_stock';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_COMPLETED_WITH_ERRORS,
        self::STATUS_FAILED,
    ];

    protected $table = 'catalog_upload';

    protected $fillable = [
        'seller_id',
        'seller_user_id',
        'type',
        'catalog_category_id',
        'save_as_draft',
        'file_path',
        'original_name',
        'status',
        'total_rows',
        'success_rows',
        'failed_rows',
        'error_report_path',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'seller_user_id' => 'integer',
            'catalog_category_id' => 'integer',
            'save_as_draft' => 'integer',
            'total_rows' => 'integer',
            'success_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<SellerUser, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(SellerUser::class, 'seller_user_id');
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return HasMany<CatalogUploadRowError, $this>
     */
    public function rowErrors(): HasMany
    {
        return $this->hasMany(CatalogUploadRowError::class, 'catalog_upload_id')->orderBy('row_number')->orderBy('id');
    }

    public function isNewCatalog(): bool
    {
        return $this->type === self::TYPE_NEW_CATALOG;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_ERRORS, self::STATUS_FAILED], true);
    }

    public function savesAsDraft(): bool
    {
        return $this->save_as_draft === (int) config('core.yes');
    }

    public function statusLabel(): string
    {
        return trans('product::seller.uploads.statuses.'.$this->status);
    }

    /**
     * Chip variant in the supplier panel.
     */
    public function statusChip(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_COMPLETED_WITH_ERRORS => 'deal',
            self::STATUS_FAILED => 'danger',
            self::STATUS_PROCESSING => 'accent',
            default => 'neutral',
        };
    }

    protected static function newFactory(): CatalogUploadFactory
    {
        return CatalogUploadFactory::new();
    }
}
