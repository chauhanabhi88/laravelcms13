<?php

declare(strict_types=1);

namespace Modules\Product\Support\Import;

use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Product\Services\ProductFactoryService;

/**
 * Columns of the bulk catalog template for one leaf category (FR-SCT-003): fixed product /
 * variant columns, then one column per mapped attribute (variant axes first, then
 * specifications). Shared by CategoryTemplateExport and the importer, so both always agree.
 *
 * A column is ['key', 'heading', 'required', 'type', 'options', 'attribute_id', 'multiple',
 * 'hint']; type is text | number | integer | list. List columns get a dropdown that reads
 * its values from the "Options" sheet.
 *
 * @phpstan-type Column array{key: string, heading: string, required: bool, type: string, options: list<string>, attribute_id: int|null, multiple: bool, hint: string}
 */
class CatalogTemplate
{
    public const MAX_IMAGES = 8;

    /**
     * Group ID of the example row; rows with it are skipped on import.
     */
    public const EXAMPLE_GROUP = 'EXAMPLE';

    /**
     * Separator of several values in one cell (highlights, multi-select attributes).
     */
    public const SEPARATOR = '|';

    /**
     * @var list<Column>
     */
    protected array $columns;

    /**
     * @var array<int, ResolvedAttribute>
     */
    protected array $attributes;

    public function __construct(
        public readonly CatalogCategory $category,
        CategoryAttributeResolver $resolver,
    ) {
        $resolved = $resolver->for($category);
        $this->attributes = $resolved->keyBy('attributeId')->all();
        $this->columns = $this->build($resolved->sortBy([fn (ResolvedAttribute $a, ResolvedAttribute $b): int => [! $a->isVariantAxis, $a->sortOrder, $a->attributeId] <=> [! $b->isVariantAxis, $b->sortOrder, $b->attributeId]])->values()->all());
    }

    public static function for(CatalogCategory $category): self
    {
        return new self($category, app(CategoryAttributeResolver::class));
    }

    /**
     * @return list<Column>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(fn (array $column): string => $column['heading'].($column['required'] ? '*' : ''), $this->columns);
    }

    /**
     * @return Column|null
     */
    public function column(string $key): ?array
    {
        foreach ($this->columns as $column) {
            if ($column['key'] === $key) {
                return $column;
            }
        }

        return null;
    }

    public function attribute(int $attributeId): ?ResolvedAttribute
    {
        return $this->attributes[$attributeId] ?? null;
    }

    /**
     * @return list<ResolvedAttribute>
     */
    public function axes(): array
    {
        return array_values(array_filter($this->attributes, fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis));
    }

    /**
     * @return list<ResolvedAttribute>
     */
    public function specs(): array
    {
        return array_values(array_filter($this->attributes, fn (ResolvedAttribute $attribute): bool => ! $attribute->isVariantAxis));
    }

    /**
     * Map a file's heading row to column keys: position => key. Headings match without the
     * required "*", case and extra spaces; unknown headings are ignored.
     *
     * @param  list<mixed>  $headings
     * @return array<int, string>
     */
    public function mapHeadings(array $headings): array
    {
        $byHeading = [];
        foreach ($this->columns as $column) {
            $byHeading[self::normalise($column['heading'])] = $column['key'];
        }

        $map = [];
        foreach ($headings as $position => $heading) {
            $key = $byHeading[self::normalise((string) $heading)] ?? null;

            if ($key !== null && ! in_array($key, $map, true)) {
                $map[(int) $position] = $key;
            }
        }

        return $map;
    }

    /**
     * Headings of required columns missing from a mapped heading row.
     *
     * @param  array<int, string>  $map  from mapHeadings()
     * @return list<string>
     */
    public function missingRequired(array $map): array
    {
        return array_values(array_map(
            fn (array $column): string => $column['heading'],
            array_filter($this->columns, fn (array $column): bool => $column['required'] && ! in_array($column['key'], $map, true)),
        ));
    }

    /**
     * A filled-in example row, keyed like columns().
     *
     * @return array<string, string>
     */
    public function exampleRow(): array
    {
        $row = [];

        foreach ($this->columns as $column) {
            $row[$column['key']] = match ($column['key']) {
                'group_id' => self::EXAMPLE_GROUP,
                'product_name' => trans('product::seller.template.example.name'),
                'description' => trans('product::seller.template.example.description'),
                'highlights' => trans('product::seller.template.example.highlights'),
                'country_of_origin' => 'IN',
                'image_url_1' => 'https://example.com/images/front.jpg',
                'seller_sku' => 'SKU-001-M',
                'mrp' => '999',
                'price' => '499',
                'stock' => '25',
                'weight_grams' => '300',
                'length_cm' => '30',
                'breadth_cm' => '25',
                'height_cm' => '3',
                default => $column['type'] === 'list' && $column['required'] ? ($column['options'][0] ?? '') : '',
            };
        }

        return $row;
    }

    public static function normalise(string $heading): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/', ' ', str_replace('*', '', $heading))));
    }

    /**
     * @param  list<ResolvedAttribute>  $attributes  axes first
     * @return list<Column>
     */
    protected function build(array $attributes): array
    {
        $column = fn (string $key, string $heading, bool $required = false, string $type = 'text', array $options = [], ?int $attributeId = null, bool $multiple = false, string $hint = ''): array => [
            'key' => $key,
            'heading' => $heading,
            'required' => $required,
            'type' => $type,
            'options' => array_values($options),
            'attribute_id' => $attributeId,
            'multiple' => $multiple,
            'hint' => $hint,
        ];
        $label = fn (string $key): string => trans('product::seller.template.columns.'.$key);
        $hint = fn (string $key): string => trans('product::seller.template.hints.'.$key);

        $columns = [
            $column('group_id', $label('group_id'), true, hint: $hint('group_id')),
            $column('product_name', $label('product_name'), true, hint: $hint('product_name')),
            $column('description', $label('description'), true, hint: $hint('description')),
            $column('highlights', $label('highlights'), hint: $hint('highlights')),
            $column('brand', $label('brand'), type: 'list', options: $this->brandNames(), hint: $hint('brand')),
            $column('hsn_code', $label('hsn_code'), hint: $hint('hsn_code')),
            $column('gst_percent', $label('gst_percent'), type: 'list', options: array_map('strval', (array) config('product.gst_rates')), hint: $hint('gst_percent')),
            $column('country_of_origin', $label('country_of_origin'), hint: $hint('country_of_origin')),
            $column('manufacturer_name', $label('manufacturer_name'), hint: $hint('manufacturer_name')),
            $column('manufacturer_address', $label('manufacturer_address'), hint: $hint('manufacturer_address')),
        ];

        for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
            $columns[] = $column('image_url_'.$i, trans('product::seller.template.columns.image_url', ['n' => $i]), $i === 1, hint: $hint('image_url'));
        }

        array_push(
            $columns,
            $column('seller_sku', $label('seller_sku'), true, hint: $hint('seller_sku')),
            $column('mrp', $label('mrp'), true, 'number', hint: $hint('mrp')),
            $column('price', $label('price'), true, 'number', hint: $hint('price')),
            $column('stock', $label('stock'), true, 'integer', hint: $hint('stock')),
            $column('weight_grams', $label('weight_grams'), true, 'integer', hint: $hint('weight_grams')),
            $column('length_cm', $label('length_cm'), type: 'number', hint: $hint('dimension')),
            $column('breadth_cm', $label('breadth_cm'), type: 'number', hint: $hint('dimension')),
            $column('height_cm', $label('height_cm'), type: 'number', hint: $hint('dimension')),
        );

        $taken = array_map(fn (array $existing): string => self::normalise($existing['heading']), $columns);

        foreach ($attributes as $attribute) {
            $heading = $attribute->name;
            if (in_array(self::normalise($heading), $taken, true)) {
                $heading .= ' ('.trans('product::seller.template.attribute_suffix').')';
            }
            $taken[] = self::normalise($heading);

            $isText = in_array($attribute->inputType, ProductFactoryService::TEXT_INPUT_TYPES, true);
            $isBoolean = $attribute->inputType === 'boolean';
            $multiple = ! $attribute->isVariantAxis && ! $isText && ! $isBoolean && ! in_array($attribute->inputType, ProductFactoryService::SINGLE_OPTION_INPUT_TYPES, true);
            $options = $isBoolean
                ? [trans('product::seller.template.yes'), trans('product::seller.template.no')]
                : array_map(fn (array $option): string => (string) $option['name'], $attribute->options);

            $columns[] = $column(
                'attr_'.$attribute->attributeId,
                $heading,
                $attribute->isVariantAxis || $attribute->isRequired,
                $isText ? 'text' : 'list',
                $isText ? [] : $options,
                $attribute->attributeId,
                $multiple,
                match (true) {
                    $attribute->isVariantAxis => trans('product::seller.template.hints.axis'),
                    $multiple => trans('product::seller.template.hints.multiple', ['separator' => self::SEPARATOR]),
                    default => trans('product::seller.template.hints.attribute'),
                },
            );
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    protected function brandNames(): array
    {
        return CatalogBrand::query()
            ->enabled()
            ->with('translations')
            ->orderBy('id')
            ->limit(2000)
            ->get()
            ->map(fn (CatalogBrand $brand): string => (string) $brand->name)
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
