<?php

declare(strict_types=1);

namespace Modules\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\CatalogUpload;
use Modules\Seller\Models\Seller;

/**
 * CatalogUpload::factory()->for($seller)->completed()->create();
 *
 * @extends Factory<CatalogUpload>
 */
class CatalogUploadFactory extends Factory
{
    protected $model = CatalogUpload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory()->active(),
            'seller_user_id' => null,
            'type' => CatalogUpload::TYPE_NEW_CATALOG,
            'catalog_category_id' => null,
            'save_as_draft' => config('core.no'),
            'file_path' => 'catalog_uploads/'.Str::random(40).'.xlsx',
            'original_name' => 'catalog.xlsx',
            'status' => CatalogUpload::STATUS_QUEUED,
        ];
    }

    public function priceStock(): static
    {
        return $this->state(fn (): array => ['type' => CatalogUpload::TYPE_PRICE_STOCK, 'original_name' => 'price-stock.csv']);
    }

    public function completed(int $success = 10, int $failed = 0): static
    {
        return $this->state(fn (): array => [
            'status' => $failed > 0 ? CatalogUpload::STATUS_COMPLETED_WITH_ERRORS : CatalogUpload::STATUS_COMPLETED,
            'total_rows' => $success + $failed,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
