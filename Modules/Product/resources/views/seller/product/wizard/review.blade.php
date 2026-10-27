@extends('product::seller.product.wizard.layout')

@section('step')
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.review.listing') }}</h2>
                    <a href="{{ frontRoute('seller.listing.step', [$product->id, 'details']) }}" class="text-sm font-medium text-accent hover:text-accent-hover">{{ trans('product::seller.products.actions.edit') }}</a>
                </div>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[10rem_1fr]">
                    <dt class="text-muted">{{ trans('product::seller.wizard.review.name') }}</dt>
                    <dd class="font-medium text-ink">{{ $translation['name'] ?? trans('product::seller.common.none') }}</dd>
                    <dt class="text-muted">{{ trans('product::seller.wizard.review.category') }}</dt>
                    <dd class="text-ink">{{ $categoryLabel }}</dd>
                    <dt class="text-muted">{{ trans('product::seller.wizard.review.brand') }}</dt>
                    <dd class="text-ink">{{ $brandName ?? trans('product::seller.wizard.details.brand_none') }}</dd>
                    <dt class="text-muted">{{ trans('product::seller.wizard.review.description') }}</dt>
                    <dd class="whitespace-pre-line text-ink-soft">{{ $translation['description'] ?? trans('product::seller.common.none') }}</dd>
                    @if (! empty($translation['highlights']))
                        <dt class="text-muted">{{ trans('product::seller.wizard.review.highlights') }}</dt>
                        <dd>
                            <ul class="list-disc space-y-0.5 pl-5 text-ink-soft">
                                @foreach ((array) $translation['highlights'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    @endif
                    <dt class="text-muted">{{ trans('product::seller.wizard.review.country') }}</dt>
                    <dd class="text-ink">{{ $draft['country_of_origin'] ?? 'IN' }}</dd>
                    <dt class="text-muted">{{ trans('product::seller.wizard.review.gst') }}</dt>
                    <dd class="text-ink">{{ ($draft['gst_percent'] ?? null) !== null ? rtrim(rtrim((string) $draft['gst_percent'], '0'), '.').'%' : trans('product::seller.common.none') }}</dd>
                </dl>
            </section>

            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.review.specs') }}</h2>
                @if ($specLines === [])
                    <p class="mt-2 text-sm text-muted">{{ trans('product::seller.common.none') }}</p>
                @else
                    <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
                        @foreach ($specLines as $label => $value)
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="text-ink">{{ $value }}</dd>
                        @endforeach
                    </dl>
                @endif
            </section>

            <section class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <div class="flex items-center justify-between gap-3 p-5 pb-0">
                    <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.review.variants') }}</h2>
                    <a href="{{ frontRoute('seller.listing.step', [$product->id, 'pricing']) }}" class="text-sm font-medium text-accent hover:text-accent-hover">{{ trans('product::seller.products.actions.edit') }}</a>
                </div>
                <table class="mt-3 min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ trans('product::seller.wizard.variants.variant') }}</th>
                            <th scope="col" class="px-4 py-2">{{ trans('product::seller.wizard.columns.seller_sku') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ trans('product::seller.wizard.columns.mrp') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ trans('product::seller.wizard.columns.price') }}</th>
                            <th scope="col" class="px-4 py-2 text-right">{{ trans('product::seller.wizard.columns.stock') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($rows as $row)
                            <tr @class(['opacity-60' => $row['status'] !== (int) config('core.enabled')])>
                                <td class="px-4 py-2 text-ink">{{ $row['label'] }}</td>
                                <td class="px-4 py-2 font-mono text-ink-soft">{{ $row['seller_sku'] }}</td>
                                <td class="px-4 py-2 text-right text-muted tabular-nums">{{ $row['mrp'] !== '' ? \Modules\Core\Support\Money::of($row['mrp'])->format() : '—' }}</td>
                                <td class="px-4 py-2 text-right font-medium text-ink tabular-nums">{{ $row['price'] !== '' ? \Modules\Core\Support\Money::of($row['price'])->format() : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['stock'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-3 text-muted">{{ trans('product::seller.wizard.variants.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.review.photos') }}</h2>
                    <a href="{{ frontRoute('seller.listing.step', [$product->id, 'images']) }}" class="text-sm font-medium text-accent hover:text-accent-hover">{{ trans('product::seller.products.actions.edit') }}</a>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($images as $image)
                        <img src="{{ $image['url'] }}" alt="" class="h-24 w-18 rounded-md border border-line object-cover" loading="lazy">
                    @empty
                        <p class="text-sm text-muted">{{ trans('product::seller.common.none') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            @if ($product->qc_notes && $product->status === \Modules\Product\Enums\ProductStatus::Rejected)
                <div class="rounded-card border border-danger/25 bg-danger-soft p-4 text-sm">
                    <h2 class="font-semibold text-danger">{{ trans('product::seller.wizard.review.qc_notes') }}</h2>
                    <p class="mt-1 whitespace-pre-line text-ink-soft">{{ $product->qc_notes }}</p>
                </div>
            @endif

            <div class="rounded-card border border-line bg-surface p-5 shadow-card">
                @if ($submittable)
                    @if ($missing === [])
                        <p class="flex items-start gap-2 text-sm text-success"><x-theme::icon name="check-circle" size="size-5" /> {{ trans('product::seller.wizard.review.ready') }}</p>
                    @else
                        <h2 class="text-sm font-semibold text-ink">{{ trans('product::seller.wizard.review.missing_title') }}</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-danger">
                            @foreach ($missing as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ frontRoute('seller.listing.submit', [$product->id]) }}" class="mt-5" x-data="{ loading: false }" x-on:submit="loading = true">
                        @csrf
                        @if ($missing === [])
                            <x-theme::button class="w-full" loading="loading">
                                {{ $product->status === \Modules\Product\Enums\ProductStatus::Rejected ? trans('product::seller.wizard.review.resubmit') : trans('product::seller.wizard.review.submit') }}
                            </x-theme::button>
                        @else
                            <x-theme::button class="w-full" disabled>
                                {{ $product->status === \Modules\Product\Enums\ProductStatus::Rejected ? trans('product::seller.wizard.review.resubmit') : trans('product::seller.wizard.review.submit') }}
                            </x-theme::button>
                        @endif
                    </form>
                    <x-theme::button variant="secondary" class="mt-3 w-full" :href="frontRoute('seller.product.index', [['tab' => $product->status->value]])">{{ trans('product::seller.wizard.review.save_draft') }}</x-theme::button>
                @else
                    <p class="text-sm text-ink-soft">{{ trans('product::seller.wizard.review.published_note') }}</p>
                    <x-theme::button variant="secondary" class="mt-4 w-full" :href="frontRoute('seller.product.index')">{{ trans('product::seller.wizard.review.back_to_products') }}</x-theme::button>
                @endif
            </div>
        </aside>
    </div>
@endsection
