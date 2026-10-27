<?php

declare(strict_types=1);

namespace Modules\Product\DTO;

use Modules\Core\Support\Money;
use Modules\Product\Models\ProductVariant;

/**
 * One variant as a seller submitted it (listing form, matrix, bulk upload).
 *
 * $id is null for a new variant and the existing variant's id on update.
 */
final readonly class VariantData
{
    /**
     * @param  array<int, int>  $options  variant-axis attribute id => option id
     */
    public function __construct(
        public string $sellerSku,
        public Money $mrp,
        public Money $price,
        public int $stock = 0,
        public array $options = [],
        public int $weightGrams = 0,
        public ?string $lengthCm = null,
        public ?string $breadthCm = null,
        public ?string $heightCm = null,
        public ?int $lowStockThreshold = null,
        public ?string $barcode = null,
        public bool $active = true,
        public ?int $id = null,
        public ?int $position = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  snake_case keys, as in a request or import row
     */
    public static function fromArray(array $data): self
    {
        $decimal = fn (string $key): ?string => isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null;

        $options = [];
        foreach ((array) ($data['options'] ?? []) as $attributeId => $optionId) {
            $options[(int) $attributeId] = (int) $optionId;
        }

        return new self(
            sellerSku: trim((string) ($data['seller_sku'] ?? '')),
            mrp: Money::of((string) ($data['mrp'] ?? '0')),
            price: Money::of((string) ($data['price'] ?? '0')),
            stock: (int) ($data['stock'] ?? 0),
            options: $options,
            weightGrams: (int) ($data['weight_grams'] ?? 0),
            lengthCm: $decimal('length_cm'),
            breadthCm: $decimal('breadth_cm'),
            heightCm: $decimal('height_cm'),
            lowStockThreshold: isset($data['low_stock_threshold']) && $data['low_stock_threshold'] !== '' ? (int) $data['low_stock_threshold'] : null,
            barcode: isset($data['barcode']) && $data['barcode'] !== '' ? trim((string) $data['barcode']) : null,
            active: ! array_key_exists('status', $data) || (int) $data['status'] === (int) config('core.enabled'),
            id: isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null,
            position: isset($data['position']) && $data['position'] !== '' ? (int) $data['position'] : null,
        );
    }

    public function combinationKey(): string
    {
        return ProductVariant::combinationKeyFor($this->options);
    }

    /**
     * JSON-safe form, used for new variants held in a product's pending changes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'seller_sku' => $this->sellerSku,
            'mrp' => $this->mrp->toDecimal(),
            'price' => $this->price->toDecimal(),
            'stock' => $this->stock,
            'options' => $this->options,
            'weight_grams' => $this->weightGrams,
            'length_cm' => $this->lengthCm,
            'breadth_cm' => $this->breadthCm,
            'height_cm' => $this->heightCm,
            'low_stock_threshold' => $this->lowStockThreshold,
            'barcode' => $this->barcode,
            'status' => $this->active ? (int) config('core.enabled') : (int) config('core.disabled'),
            'position' => $this->position,
        ];
    }
}
