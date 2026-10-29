<?php

declare(strict_types=1);

namespace Modules\Report\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A queued report export (plan M19 §3.3).
 *
 * @property int $id
 * @property int $user_id
 * @property string $report
 * @property string $format
 * @property array<string, mixed> $filters range + filter + sort query parameters
 * @property string $status
 * @property int|null $rows
 * @property string|null $path
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 */
class ReportExport extends MarketplaceModel
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'report_export';

    protected $fillable = ['user_id', 'report', 'format', 'filters', 'status', 'rows', 'path', 'error', 'completed_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'filters' => 'array',
            'rows' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->path !== null;
    }
}
