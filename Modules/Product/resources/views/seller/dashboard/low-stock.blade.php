{{-- Supplier dashboard card (FR-SCT-007); data from Modules\Product\Composers\LowStockCardComposer. --}}
@if ($lowStockVisible)
    <x-theme::stat-card :label="trans('product::seller.dashboard.low_stock')"
        :value="trans_choice('product::seller.dashboard.low_stock_value', $lowStockCount, ['count' => $lowStockCount])"
        icon="package" :href="frontRoute('seller.inventory.index', [['stock' => 'low']])" />
@endif
