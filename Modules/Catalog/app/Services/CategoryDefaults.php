<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Support\CatalogCache;
use Modules\Catalog\Support\CatalogSettings;

/**
 * Effective commerce defaults of a category: its own value, else the nearest ancestor's,
 * else the marketplace setting (GST %, return window, return type, dispatch SLA).
 *
 * @phpstan-type Defaults array{gst_percent: string|null, hsn_code: string|null, return_window_days: int, return_type: string, dispatch_sla_hours: int, size_chart_id: int|null, min_selling_price: string|null}
 */
class CategoryDefaults
{
    public function __construct(protected CatalogCache $cache) {}

    /**
     * @return Defaults
     */
    public function for(CatalogCategory|int $category): array
    {
        $category = $category instanceof CatalogCategory ? $category : CatalogCategory::query()->findOrFail($category);

        return $this->cache->remember('defaults.'.$category->id, fn (): array => $this->resolve($category->pathIds() ?: [$category->id]));
    }

    /**
     * What a child of $parentId inherits (null = a root category: the marketplace settings).
     * Shown as "Inherited: ..." placeholders on the category form.
     *
     * @return Defaults
     */
    public function inheritedFrom(?int $parentId): array
    {
        return $parentId === null ? $this->resolve([]) : $this->for($parentId);
    }

    /**
     * @param  list<int>  $pathIds  root first
     * @return Defaults
     */
    protected function resolve(array $pathIds): array
    {
        $values = array_fill_keys(CatalogCategory::INHERITABLE_FIELDS, null);

        if ($pathIds !== []) {
            $rows = CatalogCategory::query()
                ->whereIn('id', $pathIds)
                ->orderByDesc('depth')
                ->get(['id', 'depth', ...CatalogCategory::INHERITABLE_FIELDS]);

            foreach (CatalogCategory::INHERITABLE_FIELDS as $field) {
                foreach ($rows as $row) {
                    $value = $row->getAttribute($field);
                    if ($value !== null && $value !== '') {
                        $values[$field] = $value;
                        break;
                    }
                }
            }
        }

        return [
            'gst_percent' => $values['gst_percent'] !== null ? (string) $values['gst_percent'] : CatalogSettings::defaultGstPercent(),
            'hsn_code' => $values['hsn_code'] !== null ? (string) $values['hsn_code'] : null,
            'return_window_days' => $values['return_window_days'] !== null ? (int) $values['return_window_days'] : CatalogSettings::defaultReturnWindow(),
            'return_type' => $values['return_type'] !== null ? (string) $values['return_type'] : CatalogSettings::defaultReturnType(),
            'dispatch_sla_hours' => $values['dispatch_sla_hours'] !== null ? (int) $values['dispatch_sla_hours'] : CatalogSettings::defaultDispatchSlaHours(),
            'size_chart_id' => $values['size_chart_id'] !== null ? (int) $values['size_chart_id'] : null,
            'min_selling_price' => $values['min_selling_price'] !== null ? (string) $values['min_selling_price'] : null,
        ];
    }
}
