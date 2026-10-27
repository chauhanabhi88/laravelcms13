<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\CatalogSizeChart;

/**
 * @extends Factory<CatalogSizeChart>
 */
class CatalogSizeChartFactory extends Factory
{
    protected $model = CatalogSizeChart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Size Chart '.fake()->unique()->numberBetween(1, 99999),
            'unit' => 'in',
            'columns' => ['Bust', 'Waist', 'Length'],
            'rows' => [
                ['size' => 'S', 'values' => ['34', '28', '44']],
                ['size' => 'M', 'values' => ['36', '30', '44']],
                ['size' => 'L', 'values' => ['38', '32', '45']],
            ],
            'status' => config('core.enabled'),
        ];
    }
}
