<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * One problem in a bulk upload row ("row 0" = the whole file). Warnings did not stop the
 * row from being saved.
 *
 * @property int $id
 * @property int $catalog_upload_id
 * @property int $row_number
 * @property string|null $column
 * @property string $level
 * @property string $message
 * @property Carbon $created_at
 */
class CatalogUploadRowError extends MarketplaceModel
{
    public const LEVEL_ERROR = 'error';

    public const LEVEL_WARNING = 'warning';

    public const UPDATED_AT = null;

    protected $table = 'catalog_upload_row_error';

    protected $fillable = ['catalog_upload_id', 'row_number', 'column', 'level', 'message'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'catalog_upload_id' => 'integer',
            'row_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogUpload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(CatalogUpload::class, 'catalog_upload_id');
    }

    public function isWarning(): bool
    {
        return $this->level === self::LEVEL_WARNING;
    }
}
