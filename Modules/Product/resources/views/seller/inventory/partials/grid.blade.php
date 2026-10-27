{{-- Variant rows of the inventory screen (also returned by seller.inventory.filters). Rows edit inline via inventoryRow(). --}}
@php
    $enabled = (int) config('core.enabled');
    $disabled = (int) config('core.disabled');
    $cellInput = 'block h-9 w-20 rounded-md border bg-surface px-2 text-right text-sm text-ink tabular-nums focus:border-accent focus:ring-2 focus:ring-accent/15 focus:outline-none';
@endphp
@if ($variants->isEmpty())
    <x-theme::empty-state icon="grid" :title="trans('product::seller.inventory.empty.title')" :text="trans('product::seller.inventory.empty.text')" />
@else
    <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ trans('product::seller.inventory.columns.product') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('product::seller.inventory.columns.seller_sku') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.inventory.columns.price') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.inventory.columns.stock') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.inventory.columns.reserved') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.inventory.columns.available') }}</th>
                    <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.inventory.columns.threshold') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('product::seller.inventory.columns.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($variants as $variant)
                    <tr x-data="inventoryRow({ url: @js(frontRoute('seller.inventory.update', [$variant->id])), stock: {{ $variant->stock }}, threshold: {{ $variant->low_stock_threshold }}, status: {{ $variant->status }}, available: {{ $variant->available }}, reserved: {{ $variant->reserved }} })">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ getImageUrl(['module' => config('product.upload.folder'), 'image' => $variant->product?->primary_image, 'defualt-image' => true]) }}" alt=""
                                    class="h-12 w-9 shrink-0 rounded border border-line bg-surface-alt object-cover" loading="lazy">
                                <div class="min-w-0">
                                    <span class="line-clamp-1 font-medium text-ink">{{ $variant->product?->name }}</span>
                                    <span class="text-xs text-muted">{{ $variantLabels[$variant->id] ?: trans('product::seller.wizard.variants.single') }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-ink-soft">{{ $variant->seller_sku }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">{{ $variant->price->format() }}</td>
                        <td class="px-4 py-3 text-right">
                            <input type="number" min="{{ $variant->reserved }}" step="1" x-model.number="stock" class="{{ $cellInput }} border-line-strong ml-auto"
                                x-on:change="save({ stock: stock })" x-on:keydown.enter.prevent="$event.target.blur()" x-bind:disabled="saving"
                                aria-label="{{ $variant->seller_sku }} – {{ trans('product::seller.inventory.columns.stock') }}">
                        </td>
                        <td class="px-4 py-3 text-right text-muted tabular-nums" x-text="reserved">{{ $variant->reserved }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                            <span class="font-semibold" x-bind:class="available <= 0 ? 'text-danger' : (low ? 'text-deal' : 'text-ink')" x-text="available">{{ $variant->available }}</span>
                            <x-theme::chip variant="danger" class="ml-1" x-show="available <= 0" x-cloak>{{ trans('product::seller.inventory.out') }}</x-theme::chip>
                            <x-theme::chip variant="deal" class="ml-1" x-show="available > 0 && low" x-cloak>{{ trans('product::seller.inventory.low') }}</x-theme::chip>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <input type="number" min="0" step="1" x-model.number="threshold" class="{{ $cellInput }} border-line-strong ml-auto"
                                x-on:change="save({ low_stock_threshold: threshold })" x-on:keydown.enter.prevent="$event.target.blur()" x-bind:disabled="saving"
                                aria-label="{{ $variant->seller_sku }} – {{ trans('product::seller.inventory.columns.threshold') }}">
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" class="size-4 accent-accent" x-bind:checked="status === {{ $enabled }}" @checked($variant->status === $enabled)
                                    x-on:change="save({ status: $event.target.checked ? {{ $enabled }} : {{ $disabled }} })" x-bind:disabled="saving">
                                <span x-text="status === {{ $enabled }} ? @js(trans('product::seller.inventory.enabled')) : @js(trans('product::seller.inventory.disabled'))">{{ $variant->isEnabled() ? trans('product::seller.inventory.enabled') : trans('product::seller.inventory.disabled') }}</span>
                            </label>
                            <span class="ml-2 text-xs text-success" x-show="saved" x-cloak>{{ trans('product::seller.common.saved') }}</span>
                            <p class="mt-1 max-w-48 text-xs whitespace-normal text-danger" x-show="error" x-text="error" x-cloak></p>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $variants->links() }}</div>
@endif
