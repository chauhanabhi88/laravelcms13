<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Catalog\Database\Factories\CatalogSizeChartFactory;
use Modules\Core\Models\MarketplaceModel;

/**
 * Measurement grid shown on the product page (M07): one row per size, one value per column.
 *
 * @property int $id
 * @property string $name
 * @property string $unit
 * @property list<string> $columns
 * @property list<array{size: string, values: list<string>}> $rows
 * @property string|null $image
 * @property int $status
 */
class CatalogSizeChart extends MarketplaceModel
{
    /** @use HasFactory<CatalogSizeChartFactory> */
    use HasFactory;

    protected $table = 'catalog_size_chart';

    protected $fillable = ['name', 'unit', 'columns', 'rows', 'image', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'rows' => 'array',
            'status' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function sizes(): array
    {
        return array_values(array_map(fn (array $row): string => (string) ($row['size'] ?? ''), (array) $this->rows));
    }

    protected static function newFactory(): CatalogSizeChartFactory
    {
        return CatalogSizeChartFactory::new();
    }
}
