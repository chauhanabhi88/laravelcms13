<?php

declare(strict_types=1);

namespace Modules\Product\DTO;

/**
 * A whole product as a seller submitted it: content, tax details, specification
 * attributes, variants and images. Built by the listing form and the bulk importer (M05)
 * and written atomically by ProductFactoryService.
 *
 * @phpstan-type Translation array{name?: string|null, description?: string|null, highlights?: list<string>|null, meta_title?: string|null, meta_description?: string|null}
 * @phpstan-type Party array{name?: string|null, address?: string|null}
 */
final readonly class ProductData
{
    /**
     * @param  array<string, Translation>  $translations  locale => fields
     * @param  list<VariantData>  $variants
     * @param  list<ImageData>  $images  in display order; the first general image is primary
     * @param  array<int, int|list<int>|string|null>  $attributes  specification attribute id => option id(s), or text
     * @param  Party|null  $manufacturerDetails
     * @param  Party|null  $packerDetails
     * @param  Party|null  $importerDetails
     */
    public function __construct(
        public int $sellerId,
        public int $categoryId,
        public array $translations,
        public array $variants = [],
        public array $images = [],
        public array $attributes = [],
        public ?int $brandId = null,
        public ?string $hsnCode = null,
        public ?string $gstPercent = null,
        public string $countryOfOrigin = 'IN',
        public ?array $manufacturerDetails = null,
        public ?array $packerDetails = null,
        public ?array $importerDetails = null,
        public ?int $returnWindowDays = null,
        public ?string $returnType = null,
        public bool $isCodAllowed = true,
        public ?string $searchKeywords = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  snake_case keys; variants / images as nested arrays
     */
    public static function fromArray(array $data): self
    {
        $nullable = fn (string $key): ?string => isset($data[$key]) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;
        $party = fn (string $key): ?array => is_array($data[$key] ?? null) && array_filter($data[$key], fn ($value) => $value !== null && $value !== '') !== []
            ? array_map(fn ($value) => $value === null ? null : trim((string) $value), $data[$key])
            : null;

        $attributes = [];
        foreach ((array) ($data['attributes'] ?? []) as $attributeId => $value) {
            $attributes[(int) $attributeId] = $value;
        }

        return new self(
            sellerId: (int) ($data['seller_id'] ?? 0),
            categoryId: (int) ($data['catalog_category_id'] ?? 0),
            translations: (array) ($data['translations'] ?? []),
            variants: array_values(array_map(
                fn ($variant): VariantData => $variant instanceof VariantData ? $variant : VariantData::fromArray((array) $variant),
                (array) ($data['variants'] ?? []),
            )),
            images: array_values(array_map(
                fn ($image): ImageData => $image instanceof ImageData ? $image : ImageData::fromArray((array) $image),
                (array) ($data['images'] ?? []),
            )),
            attributes: $attributes,
            brandId: isset($data['catalog_brand_id']) && $data['catalog_brand_id'] !== '' ? (int) $data['catalog_brand_id'] : null,
            hsnCode: $nullable('hsn_code'),
            gstPercent: $nullable('gst_percent'),
            countryOfOrigin: strtoupper($nullable('country_of_origin') ?? 'IN'),
            manufacturerDetails: $party('manufacturer_details'),
            packerDetails: $party('packer_details'),
            importerDetails: $party('importer_details'),
            returnWindowDays: isset($data['return_window_days']) && $data['return_window_days'] !== '' ? (int) $data['return_window_days'] : null,
            returnType: $nullable('return_type'),
            isCodAllowed: ! array_key_exists('is_cod_allowed', $data) || (int) $data['is_cod_allowed'] === (int) config('core.yes'),
            searchKeywords: $nullable('search_keywords'),
        );
    }
}
