<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

/**
 * One effective attribute of a category, after inheritance (CategoryAttributeResolver).
 *
 * @phpstan-type Option array{id: int, code: string|null, name: string, image: string|null, sort_order: int}
 */
final readonly class ResolvedAttribute
{
    /**
     * @param  list<int>|null  $allowedOptionIds  null = every option of the attribute
     * @param  list<Option>  $options  the options a seller may pick (already restricted)
     */
    public function __construct(
        public int $attributeId,
        public string $code,
        public string $name,
        public string $inputType,
        public bool $isVariantAxis,
        public bool $isFilterable,
        public bool $isRequired,
        public bool $showOnCard,
        public int $sortOrder,
        public ?array $allowedOptionIds,
        public array $options,
        public int $sourceCategoryId,
        public bool $inherited,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attribute_id' => $this->attributeId,
            'code' => $this->code,
            'name' => $this->name,
            'input_type' => $this->inputType,
            'is_variant_axis' => $this->isVariantAxis,
            'is_filterable' => $this->isFilterable,
            'is_required' => $this->isRequired,
            'show_on_card' => $this->showOnCard,
            'sort_order' => $this->sortOrder,
            'allowed_option_ids' => $this->allowedOptionIds,
            'options' => $this->options,
            'source_category_id' => $this->sourceCategoryId,
            'inherited' => $this->inherited,
        ];
    }
}
