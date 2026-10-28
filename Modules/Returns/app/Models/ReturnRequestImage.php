<?php

declare(strict_types=1);

namespace Modules\Returns\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A customer photo of a return (private disk, re-encoded on upload).
 *
 * @property int $id
 * @property int $return_request_id
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $created_at
 */
class ReturnRequestImage extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'return_request_image';

    protected $fillable = ['return_request_id', 'path', 'width', 'height'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_request_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ReturnRequest, $this>
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }
}
