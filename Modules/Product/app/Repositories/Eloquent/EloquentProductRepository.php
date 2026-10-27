<?php

namespace Modules\Product\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Core\Support\Money;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Support\ProductSettings;

class EloquentProductRepository extends EloquentBaseRepository implements ProductRepository
{
    /**
     * Grid column code => database column.
     */
    private const SORTABLE = [
        'id' => 'id',
        'price' => 'min_price',
        'total_stock' => 'total_stock',
        'status' => 'status',
        'published_at' => 'published_at',
        'submitted_at' => 'submitted_at',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    private const STOCK_FILTERS = ['in_stock', 'low_stock', 'out_of_stock'];

    public function getFilters(Request $request, string $grid): array
    {
        $sessionKey = $this->sessionKey($grid);
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $select = ['' => trans('core::core.labels.select')];

        $filters = [
            [
                'type' => 'text',
                'name' => 'search',
                'row' => '1',
                'value' => $filter('search'),
                'options' => ['placeholder' => trans('product::product.labels.search'), 'class' => 'form-control'],
            ],
            [
                'type' => 'text',
                'name' => 'seller',
                'row' => '1',
                'value' => $filter('seller'),
                'options' => ['placeholder' => trans('product::product.labels.seller_search'), 'class' => 'form-control'],
            ],
            [
                'type' => 'select',
                'name' => 'catalog_category_id',
                'row' => '1',
                'value' => $filter('catalog_category_id'),
                'select_options' => $select + $this->categoryOptions(),
                'options' => ['label' => trans('product::product.labels.category'), 'class' => 'custom-select'],
            ],
        ];

        if ($grid === self::GRID_ALL) {
            $filters[] = [
                'type' => 'select',
                'name' => 'status',
                'row' => '1',
                'value' => $filter('status'),
                'select_options' => $select + ProductStatus::options(),
                'options' => ['label' => trans('core::core.labels.status'), 'class' => 'custom-select'],
            ];
            $filters[] = [
                'type' => 'select',
                'name' => 'stock',
                'row' => '2',
                'value' => $filter('stock'),
                'select_options' => $select + array_combine(self::STOCK_FILTERS, array_map(fn (string $key): string => trans('product::product.stock_filters.'.$key), self::STOCK_FILTERS)),
                'options' => ['label' => trans('product::product.labels.stock'), 'class' => 'custom-select'],
            ];
            $filters[] = [
                'type' => 'number_range',
                'row' => '2',
                'name' => ['price_from', 'price_to'],
                'value' => ['price_from' => $filter('price_from'), 'price_to' => $filter('price_to')],
                'options' => [
                    'price_from' => ['label' => trans('product::product.labels.price'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control'],
                    'price_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control'],
                ],
            ];
            $filters[] = [
                'type' => 'date_range',
                'row' => '2',
                'name' => ['created_at_from', 'created_at_to'],
                'value' => ['created_at_from' => $filter('created_at_from'), 'created_at_to' => $filter('created_at_to')],
                'options' => [
                    'created_at_from' => ['label' => trans('core::core.labels.created_on'), 'placeholder' => trans('core::core.labels.from'), 'class' => 'form-control datepicker'],
                    'created_at_to' => ['placeholder' => trans('core::core.labels.to'), 'class' => 'form-control datepicker'],
                ],
            ];
        } else {
            $filters[] = [
                'type' => 'select',
                'name' => 'queue_type',
                'row' => '1',
                'value' => $filter('queue_type'),
                'select_options' => $select + [
                    'new' => trans('product::product.queue_types.new'),
                    'changes' => trans('product::product.queue_types.changes'),
                ],
                'options' => ['label' => trans('product::product.labels.queue_type'), 'class' => 'custom-select'],
            ];
            $filters[] = [
                'type' => 'select',
                'name' => 'overdue',
                'row' => '2',
                'value' => $filter('overdue'),
                'select_options' => $select + [(string) config('core.yes') => trans('product::product.labels.overdue_only')],
                'options' => ['label' => trans('product::product.labels.waiting'), 'class' => 'custom-select'],
            ];
        }

        $filters[] = [
            'type' => 'action',
            'class' => 'col-action',
            'row' => '3',
            'buttons' => [
                'submit' => [
                    'name' => 'search',
                    'type' => 'submit',
                    'onclick' => 'searchFilter(); return false;',
                    'class' => 'btn btn-primary btn-fw',
                    'title' => trans('core::core.buttons.search'),
                ],
                'reset' => [
                    'name' => 'reset',
                    'type' => 'button',
                    'onclick' => "window.location.href= '".route('admin.reset_filter', updateUrlParams([$sessionKey]))."'",
                    'class' => 'btn btn-secondary btn-fw',
                    'title' => trans('core::core.buttons.reset'),
                ],
            ],
        ];

        return $filters;
    }

    public function pagination(Request $request, string $grid): LengthAwarePaginator
    {
        $sessionKey = $this->sessionKey($grid);
        $perPage = $request->input('per_page', settings('core', 'default_per_page')) ?: config('core.default_per_page');
        $queue = $grid === self::GRID_QC;

        // The QC queue is worked oldest first.
        $orderBy = self::SORTABLE[(string) $request->input('order_by')] ?? ($queue ? 'submitted_at' : 'id');
        $dir = strtoupper((string) $request->input('dir', $queue ? 'ASC' : 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $collection = $this->filter($request, $grid)
            ->with(['translations', 'seller:id,code,store_name,legal_name', 'category.translations'])
            ->orderBy('product.'.$orderBy, $dir)
            ->orderBy('product.id', $dir);
        updateSessionFilterPage($sessionKey, $collection, $perPage);

        return $collection->paginate($perPage, ['product.*'], 'page', getSessionFilter($sessionKey, 'page'));
    }

    public function filter(Request $request, string $grid): Builder
    {
        $sessionKey = $this->sessionKey($grid);
        $filter = fn (string $key) => $request->input($key, getSessionFilter($sessionKey, $key));
        $collection = $this->model->newQuery();

        if ($grid === self::GRID_QC) {
            $collection->inQcQueue();

            match ((string) $filter('queue_type')) {
                'new' => $collection->where('product.status', ProductStatus::PendingQc->value),
                'changes' => $collection->where('product.status', '!=', ProductStatus::PendingQc->value),
                default => null,
            };

            if ((int) $filter('overdue') === (int) config('core.yes')) {
                $collection->where('product.submitted_at', '<=', now()->subHours(ProductSettings::qcSlaHours()));
            }
        } elseif (($status = (string) $filter('status')) !== '' && ProductStatus::tryFrom($status) !== null) {
            $collection->where('product.status', $status);
        }

        if (($search = trim((string) $filter('search'))) !== '') {
            $collection->where(function (Builder $query) use ($search): void {
                if (ctype_digit($search)) {
                    $query->orWhere('product.id', (int) $search);
                }

                $query->orWhereHas('translations', fn (Builder $translations) => $translations->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->where('sku', 'like', '%'.$search.'%')->orWhere('seller_sku', 'like', '%'.$search.'%'));
            });
        }

        if (($seller = trim((string) $filter('seller'))) !== '') {
            $collection->whereHas('seller', function (Builder $query) use ($seller): void {
                $query->where('code', $seller)
                    ->orWhere('store_name', 'like', '%'.$seller.'%')
                    ->orWhere('legal_name', 'like', '%'.$seller.'%');
            });
        }

        if (($categoryId = (int) $filter('catalog_category_id')) > 0) {
            $collection->where('product.catalog_category_id', $categoryId);
        }

        if ($grid === self::GRID_ALL) {
            $this->applyStockFilter($collection, (string) $filter('stock'));
            $this->applyPriceFilter($collection, $filter('price_from'), '>=');
            $this->applyPriceFilter($collection, $filter('price_to'), '<=');

            $timezoneOffset = getTimezoneOffset();
            if (($from = $filter('created_at_from')) !== null && $from !== '') {
                $collection->whereRaw("DATE(product.created_at + INTERVAL {$timezoneOffset} SECOND) >= ?", [date('Y-m-d', strtotime((string) $from))]);
            }
            if (($to = $filter('created_at_to')) !== null && $to !== '') {
                $collection->whereRaw("DATE(product.created_at + INTERVAL {$timezoneOffset} SECOND) <= ?", [date('Y-m-d', strtotime((string) $to))]);
            }
        }

        return $collection;
    }

    public function findWithDetails(int $id): ?Product
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->with([
                'translations',
                'seller',
                'category.translations',
                'brand.translations',
                'variants' => fn ($query) => $query->with('attributeValues'),
                'images',
                'attributeValues',
                'statusHistories',
            ])
            ->find($id);
    }

    public function qcQueue(int $limit): Collection
    {
        return $this->model->newQuery()
            ->inQcQueue()
            ->with(['translations', 'seller:id,code,store_name,legal_name'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function exportRows(Request $request): array
    {
        $rows = [];

        $this->filter($request, self::GRID_ALL)
            ->with(['translations', 'seller:id,code,store_name,legal_name', 'category.translations'])
            ->orderBy('product.id')
            ->chunk(500, function ($products) use (&$rows): void {
                foreach ($products as $product) {
                    $rows[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'seller' => $product->seller?->displayName(),
                        'seller_code' => $product->seller?->code,
                        'category' => $product->category?->name,
                        'status' => $product->status->label(),
                        'min_price' => $product->min_price?->toDecimal(),
                        'max_price' => $product->max_price?->toDecimal(),
                        'min_mrp' => $product->min_mrp?->toDecimal(),
                        'max_discount_percent' => $product->max_discount_percent,
                        'total_stock' => $product->total_stock,
                        'gst_percent' => $product->gst_percent,
                        'hsn_code' => $product->hsn_code,
                        'published_at' => $product->published_at?->toDateTimeString(),
                        'created_at' => $product->created_at?->toDateTimeString(),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  Builder<Product>  $collection
     */
    private function applyStockFilter(Builder $collection, string $stock): void
    {
        match ($stock) {
            'in_stock' => $collection->where('product.total_stock', '>', 0),
            'out_of_stock' => $collection->where('product.total_stock', '<=', 0),
            'low_stock' => $collection->whereHas('variants', fn (Builder $variants) => $variants
                ->where('status', config('core.enabled'))
                ->whereRaw('(stock - reserved) <= low_stock_threshold')),
            default => null,
        };
    }

    /**
     * @param  Builder<Product>  $collection
     */
    private function applyPriceFilter(Builder $collection, mixed $value, string $operator): void
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return;
        }

        $collection->where($operator === '>=' ? 'product.max_price' : 'product.min_price', $operator, Money::of((string) $value)->toDecimal());
    }

    /**
     * Leaf categories (products attach only to leaves) as "Parent › Leaf" labels.
     *
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        $tree = app(CategoryTreeService::class);
        $options = [];

        foreach ($tree->nodes() as $node) {
            if ((int) $node['is_leaf'] === (int) config('core.yes')) {
                $options[$node['id']] = $tree->breadcrumb($node['id']);
            }
        }

        return $options;
    }

    private function sessionKey(string $grid): string
    {
        return (string) config($grid === self::GRID_QC ? 'product.cache.product_qc' : 'product.cache.product');
    }
}
