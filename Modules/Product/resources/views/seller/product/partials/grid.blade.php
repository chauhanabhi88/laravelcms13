{{-- Product rows of the supplier product list (also returned by seller.product.filters). --}}
@if ($products->isEmpty())
    <x-theme::empty-state icon="package" :title="trans('product::seller.products.empty.title')" :text="trans('product::seller.products.empty.text')">
        <x-theme::button :href="frontRoute('seller.listing.create')"><x-theme::icon name="plus" size="size-4" /> {{ trans('product::seller.products.add') }}</x-theme::button>
    </x-theme::empty-state>
@else
    <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ trans('product::seller.products.columns.product') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('product::seller.products.columns.category') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.products.columns.price') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.products.columns.stock') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('product::seller.products.columns.status') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($products as $product)
                    @php
                        $status = $product->status;
                        $isDraftLike = in_array($status, [\Modules\Product\Enums\ProductStatus::Draft, \Modules\Product\Enums\ProductStatus::Rejected], true);
                        $editable = \Modules\Product\Support\Listing\ListingWizard::editable($product);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ getImageUrl(['module' => config('product.upload.folder'), 'image' => $product->primary_image, 'defualt-image' => true]) }}" alt=""
                                    class="h-14 w-11 shrink-0 rounded-md border border-line bg-surface-alt object-cover" loading="lazy">
                                <div class="min-w-0">
                                    <span class="line-clamp-2 font-medium text-ink">{{ $product->name ?: trans('product::seller.common.none') }}</span>
                                    <span class="text-xs text-muted">#{{ $product->id }} · {{ trans_choice('product::seller.products.variants', $product->variants_count, ['count' => $product->variants_count]) }}</span>
                                    @if ($status === \Modules\Product\Enums\ProductStatus::Rejected && $product->qc_notes)
                                        <p class="mt-1 line-clamp-2 max-w-md text-xs text-danger">{{ trans('product::seller.products.reason', ['reason' => $product->qc_notes]) }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="max-w-56 px-4 py-3 text-ink-soft">{{ $categoryLabels[$product->catalog_category_id] ?? '' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                            @if ($product->min_price->isPositive())
                                <span class="font-medium text-ink">{{ $product->min_price->format() }}</span>
                                @if ($product->max_price->compare($product->min_price) > 0)
                                    <span class="text-muted">– {{ $product->max_price->format() }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td @class(['px-4 py-3 text-right tabular-nums', 'font-medium text-danger' => $product->total_stock <= 0 && $product->isPublished()])>{{ $product->total_stock }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col items-start gap-1">
                                <x-theme::chip :variant="$status->chip()">{{ $status->label() }}</x-theme::chip>
                                @if ($product->isPublished() && $product->hasPendingChanges())
                                    <x-theme::chip variant="accent">{{ trans('product::seller.products.changes_pending') }}</x-theme::chip>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($editable)
                                    <x-theme::button variant="secondary" size="sm" :href="$status === \Modules\Product\Enums\ProductStatus::Rejected ? frontRoute('seller.listing.step', [$product->id, 'review']) : frontRoute('seller.product.edit', [$product->id])">
                                        {{ match ($status) {
                                            \Modules\Product\Enums\ProductStatus::Draft => trans('product::seller.products.actions.continue'),
                                            \Modules\Product\Enums\ProductStatus::Rejected => trans('product::seller.products.actions.fix'),
                                            default => trans('product::seller.products.actions.edit'),
                                        } }}
                                    </x-theme::button>
                                @endif
                                @if ($status === \Modules\Product\Enums\ProductStatus::Live)
                                    <form method="POST" action="{{ frontRoute('seller.product.pause', [$product->id]) }}">
                                        @csrf
                                        <x-theme::button variant="ghost" size="sm">{{ trans('product::seller.products.actions.pause') }}</x-theme::button>
                                    </form>
                                @elseif ($status === \Modules\Product\Enums\ProductStatus::Paused)
                                    <form method="POST" action="{{ frontRoute('seller.product.resume', [$product->id]) }}">
                                        @csrf
                                        <x-theme::button variant="ghost" size="sm">{{ trans('product::seller.products.actions.resume') }}</x-theme::button>
                                    </form>
                                @endif
                                @if ($isDraftLike)
                                    <form method="POST" action="{{ frontRoute('seller.product.delete', [$product->id]) }}"
                                        x-data x-on:submit="if (! confirm(@js(trans('product::seller.products.actions.delete_confirm')))) $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <x-theme::button variant="ghost" size="sm" class="text-danger">{{ trans('product::seller.products.actions.delete') }}</x-theme::button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endif
