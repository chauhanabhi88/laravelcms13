<?php

declare(strict_types=1);

namespace Modules\Product\Support\Import;

use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Product\Services\ProductFactoryService;
use Modules\Seller\Models\Seller;

/**
 * Turns bulk catalog rows into products (FR-SCT-003): rows sharing a "Group ID" are one
 * product, each row one variant. Product columns come from the group's first row; a later
 * row with a different value gets a warning. Format problems (unknown option, bad number,
 * missing required value, SKU repeated in the file…) are errors on their row; the catalog
 * rules themselves are checked later by ProductFactoryService.
 */
class RowToProductMapper
{
    protected const PRODUCT_COLUMNS = ['product_name', 'description', 'highlights', 'brand', 'hsn_code', 'gst_percent', 'country_of_origin', 'manufacturer_name', 'manufacturer_address'];

    /**
     * @var array<string, int>|null lower-cased brand name => id
     */
    protected ?array $brands = null;

    /**
     * @param  array<int, array<string, string>>  $rows  spreadsheet row number => column key => trimmed value
     * @return list<MappedGroup>
     */
    public function map(CatalogTemplate $template, Seller $seller, array $rows): array
    {
        $groups = [];
        $order = [];

        foreach ($rows as $rowNumber => $row) {
            $groupId = trim((string) ($row['group_id'] ?? ''));

            if (strcasecmp($groupId, CatalogTemplate::EXAMPLE_GROUP) === 0) {
                continue;
            }

            $key = $groupId === '' ? '#row'.$rowNumber : Str::lower($groupId);

            if (! isset($groups[$key])) {
                $groups[$key] = ['id' => $groupId, 'rows' => []];
                $order[] = $key;
            }

            $groups[$key]['rows'][(int) $rowNumber] = $row;
        }

        $seenSkus = [];
        $mapped = [];

        foreach ($order as $key) {
            $mapped[] = $this->mapGroup($template, $seller, $groups[$key]['id'], $groups[$key]['rows'], $seenSkus);
        }

        return $mapped;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, int>  $seenSkus  lower-cased SKU => row, across the whole file
     */
    protected function mapGroup(CatalogTemplate $template, Seller $seller, string $groupId, array $rows, array &$seenSkus): MappedGroup
    {
        $group = new MappedGroup($groupId, array_keys($rows));
        $first = $group->firstRow();
        $product = $rows[$first];

        if ($groupId === '') {
            $group->addError($first, 'group_id', trans('product::seller.uploads.row_errors.group_missing'));
        }

        $maxVariants = (int) config('product.max_variants', 100);
        if (count($rows) > $maxVariants) {
            $group->addError($first, 'group_id', trans('product::seller.uploads.row_errors.too_many_variants', ['max' => $maxVariants]));
        }

        $data = [
            'catalog_category_id' => $template->category->id,
            'translations' => ['en' => [
                'name' => $this->required($group, $first, $product, 'product_name'),
                'description' => $this->required($group, $first, $product, 'description'),
                'highlights' => $this->list($product['highlights'] ?? ''),
            ]],
            'country_of_origin' => Str::upper($product['country_of_origin'] ?? '') ?: 'IN',
            'variants' => [],
            'attributes' => [],
        ];

        $locale = (string) (config('translatable.fallback_locale') ?: config('app.fallback_locale', 'en'));
        if ($locale !== 'en') {
            $data['translations'] = [$locale => $data['translations']['en']];
        }

        if (($product['brand'] ?? '') !== '') {
            $brandId = $this->brandId($product['brand'], $seller);
            if ($brandId === null) {
                $group->addError($first, 'brand', trans('product::seller.uploads.row_errors.brand_unknown', ['brand' => $product['brand']]));
            } else {
                $data['catalog_brand_id'] = $brandId;
            }
        }

        if ($seller->isGstRegistered()) {
            if (($product['hsn_code'] ?? '') !== '') {
                $data['hsn_code'] = $product['hsn_code'];
            }

            if (($product['gst_percent'] ?? '') !== '') {
                $rate = $this->decimal($product['gst_percent']);
                $allowed = array_map(fn ($value): string => number_format((float) $value, 2, '.', ''), (array) config('product.gst_rates'));

                if ($rate === null || ! in_array(number_format((float) $rate, 2, '.', ''), $allowed, true)) {
                    $group->addError($first, 'gst_percent', trans('product::seller.uploads.row_errors.gst_rate', ['rates' => implode(', ', (array) config('product.gst_rates'))]));
                } else {
                    $data['gst_percent'] = $rate;
                }
            }
        }

        if (($product['manufacturer_name'] ?? '') !== '' || ($product['manufacturer_address'] ?? '') !== '') {
            $data['manufacturer_details'] = ['name' => $product['manufacturer_name'] ?? '', 'address' => $product['manufacturer_address'] ?? ''];
        }

        foreach ($template->specs() as $attribute) {
            $value = $this->attributeValue($group, $first, $product, $attribute);

            if ($value !== null) {
                $data['attributes'][$attribute->attributeId] = $value;
            }
        }

        for ($i = 1; $i <= CatalogTemplate::MAX_IMAGES; $i++) {
            $url = $product['image_url_'.$i] ?? '';

            if ($url === '') {
                continue;
            }

            if (preg_match('#^https?://#i', $url)) {
                $group->imageUrls[] = $url;
            } else {
                $group->addError($first, 'image_url_'.$i, trans('product::seller.uploads.row_errors.image_url'));
            }
        }

        if ($group->imageUrls === []) {
            $group->addError($first, 'image_url_1', trans('product::seller.uploads.row_errors.required', ['column' => $template->column('image_url_1')['heading'] ?? 'Image URL 1']));
        }

        foreach ($rows as $rowNumber => $row) {
            $data['variants'][] = $this->variant($template, $group, $rowNumber, $row, $seenSkus);

            if ($rowNumber !== $first) {
                foreach (self::PRODUCT_COLUMNS as $column) {
                    $value = $row[$column] ?? '';

                    if ($value !== '' && $value !== ($product[$column] ?? '')) {
                        $group->addWarning($rowNumber, $column, trans('product::seller.uploads.row_errors.product_mismatch', ['column' => $template->column($column)['heading'] ?? $column]));
                    }
                }
            }
        }

        $group->data = $data;

        return $group;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $seenSkus
     * @return array<string, mixed>
     */
    protected function variant(CatalogTemplate $template, MappedGroup $group, int $rowNumber, array $row, array &$seenSkus): array
    {
        $sku = $this->required($group, $rowNumber, $row, 'seller_sku');

        if ($sku !== '') {
            $lower = Str::lower($sku);

            if (isset($seenSkus[$lower])) {
                $group->addError($rowNumber, 'seller_sku', trans('product::seller.uploads.row_errors.sku_repeated', ['row' => $seenSkus[$lower]]));
            } else {
                $seenSkus[$lower] = $rowNumber;
            }
        }

        $variant = [
            'seller_sku' => $sku,
            'mrp' => $this->money($group, $rowNumber, $row, 'mrp'),
            'price' => $this->money($group, $rowNumber, $row, 'price'),
            'stock' => $this->integer($group, $rowNumber, $row, 'stock', true),
            'weight_grams' => $this->integer($group, $rowNumber, $row, 'weight_grams', true),
            'options' => [],
        ];

        foreach (['length_cm', 'breadth_cm', 'height_cm'] as $column) {
            if (($row[$column] ?? '') === '') {
                continue;
            }

            $value = $this->decimal($row[$column]);
            if ($value === null) {
                $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.number'));
            } else {
                $variant[$column] = number_format(round((float) $value, 1), 1, '.', '');
            }
        }

        foreach ($template->axes() as $axis) {
            $column = 'attr_'.$axis->attributeId;
            $name = $row[$column] ?? '';

            if ($name === '') {
                $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.required', ['column' => $template->column($column)['heading'] ?? $axis->name]));

                continue;
            }

            $optionId = $this->optionId($axis, $name);
            if ($optionId === null) {
                $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.option_unknown', ['value' => $name, 'attribute' => $axis->name]));
            } else {
                $variant['options'][$axis->attributeId] = $optionId;
            }
        }

        return $variant;
    }

    /**
     * @param  array<string, string>  $row
     * @return int|list<int>|string|null
     */
    protected function attributeValue(MappedGroup $group, int $rowNumber, array $row, ResolvedAttribute $attribute): int|array|string|null
    {
        $column = 'attr_'.$attribute->attributeId;
        $value = $row[$column] ?? '';

        if ($value === '') {
            if ($attribute->isRequired) {
                $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.required', ['column' => $attribute->name]));
            }

            return null;
        }

        if (in_array($attribute->inputType, ProductFactoryService::TEXT_INPUT_TYPES, true)) {
            return $value;
        }

        if ($attribute->inputType === 'boolean') {
            $flag = match (Str::lower($value)) {
                Str::lower(trans('product::seller.template.yes')), 'yes', 'y', '1', 'true' => (int) config('core.yes'),
                Str::lower(trans('product::seller.template.no')), 'no', 'n', '2', '0', 'false' => (int) config('core.no'),
                default => null,
            };

            if ($flag === null) {
                $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.option_unknown', ['value' => $value, 'attribute' => $attribute->name]));
            }

            return $flag === null ? null : (string) $flag;
        }

        $ids = [];
        foreach ($this->list($value) as $name) {
            $optionId = $this->optionId($attribute, $name);

            if ($optionId === null) {
                $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.option_unknown', ['value' => $name, 'attribute' => $attribute->name]));

                return null;
            }

            $ids[] = $optionId;
        }

        return $ids === [] ? null : array_values(array_unique($ids));
    }

    protected function optionId(ResolvedAttribute $attribute, string $name): ?int
    {
        $needle = Str::lower(trim($name));

        foreach ($attribute->options as $option) {
            if (Str::lower((string) $option['name']) === $needle || ($option['code'] !== null && Str::lower((string) $option['code']) === $needle)) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    protected function brandId(string $name, Seller $seller): ?int
    {
        if ($this->brands === null) {
            $this->brands = [];

            CatalogBrand::query()
                ->where(fn ($query) => $query->where('status', config('core.enabled'))->orWhere('requested_by_seller_id', $seller->id))
                ->with('translations')
                ->get()
                ->each(function (CatalogBrand $brand): void {
                    foreach ($brand->translations as $translation) {
                        $this->brands[Str::lower((string) $translation->name)] ??= $brand->id;
                    }
                });
        }

        return $this->brands[Str::lower(trim($name))] ?? null;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function required(MappedGroup $group, int $rowNumber, array $row, string $column): string
    {
        $value = $row[$column] ?? '';

        if ($value === '') {
            $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.required', ['column' => trans('product::seller.template.columns.'.$column)]));
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function money(MappedGroup $group, int $rowNumber, array $row, string $column): string
    {
        $value = $this->required($group, $rowNumber, $row, $column);

        if ($value === '') {
            return '0';
        }

        $decimal = $this->decimal($value);
        if ($decimal === null || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $decimal)) {
            $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.money'));

            return '0';
        }

        return $decimal;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function integer(MappedGroup $group, int $rowNumber, array $row, string $column, bool $required): int
    {
        $value = $required ? $this->required($group, $rowNumber, $row, $column) : ($row[$column] ?? '');

        if ($value === '') {
            return 0;
        }

        $decimal = $this->decimal($value);
        if ($decimal === null || ! preg_match('/^\d{1,9}(\.0+)?$/', $decimal)) {
            $group->addError($rowNumber, $column, trans('product::seller.uploads.row_errors.integer'));

            return 0;
        }

        return (int) $decimal;
    }

    /**
     * A plain non-negative decimal ("1,299.50" → "1299.50"), or null.
     */
    protected function decimal(string $value): ?string
    {
        $value = str_replace([',', '₹', ' '], '', trim($value));

        return preg_match('/^\d+(\.\d+)?$/', $value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    protected function list(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(CatalogTemplate::SEPARATOR, $value)), fn (string $item): bool => $item !== ''));
    }
}
