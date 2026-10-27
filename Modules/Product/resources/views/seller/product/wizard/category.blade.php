@extends('product::seller.product.wizard.layout')

@section('step')
    <form method="POST" action="{{ frontRoute('seller.listing.category') }}" class="space-y-5"
        x-data="categoryPicker({ url: @js(frontRoute('seller.category.search')), selectedId: @js($selectedId), selectedLabel: @js($selectedLabel ?? '') })">
        @csrf
        @if ($product)
            <input type="hidden" name="product" value="{{ $product->id }}">
        @endif
        <input type="hidden" name="catalog_category_id" x-bind:value="selectedId" value="{{ old('catalog_category_id', $selectedId) }}">

        <div class="rounded-card border border-line bg-surface p-5 shadow-card">
            <p class="text-sm text-ink-soft">{{ trans('product::seller.wizard.category.intro') }}</p>

            <div class="relative mt-4 max-w-2xl" x-on:click.outside="open = false">
                <x-theme::label for="category-search">{{ trans('product::seller.wizard.category.search') }}</x-theme::label>
                <div class="relative">
                    <x-theme::icon name="search" size="size-4" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-muted" />
                    <input id="category-search" type="search" autocomplete="off" x-model="query" x-on:input="search()" x-on:focus="open = results.length > 0"
                        placeholder="{{ trans('product::seller.wizard.category.search_placeholder') }}"
                        class="block h-11 w-full rounded-lg border border-line-strong bg-surface pr-3.5 pl-10 text-sm text-ink shadow-soft placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none">
                </div>
                <ul x-show="open" x-cloak role="listbox" class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-line bg-surface py-1 shadow-pop">
                    <template x-for="leaf in results" :key="leaf.id">
                        <li>
                            <button type="button" x-on:click="pick(leaf)" class="block w-full px-3.5 py-2 text-left text-sm hover:bg-accent-soft">
                                <span class="block font-medium text-ink" x-text="leaf.name"></span>
                                <span class="block text-xs text-muted" x-text="leaf.breadcrumb"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="results.length === 0" class="px-3.5 py-2 text-sm text-muted">{{ trans('product::seller.wizard.category.no_results') }}</li>
                </ul>
            </div>

            <div class="mt-5 rounded-lg bg-surface-alt px-4 py-3 text-sm">
                <span class="text-muted">{{ trans('product::seller.wizard.category.selected') }}:</span>
                <span class="font-medium text-ink" x-text="selectedLabel || @js(trans('product::seller.wizard.category.none_selected'))">{{ $selectedLabel ?: trans('product::seller.wizard.category.none_selected') }}</span>
            </div>
            <x-theme::field-error name="catalog_category_id" />
        </div>

        <div class="rounded-card border border-line bg-surface p-5 shadow-card">
            <h2 class="text-base font-semibold text-ink">{{ trans('product::seller.wizard.category.browse') }}</h2>
            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($tree as $root)
                    <details class="rounded-lg border border-line px-3 py-2">
                        <summary class="cursor-pointer text-sm font-semibold text-ink">{{ $root['name'] }}</summary>
                        <div class="mt-2 space-y-1">
                            @include('product::seller.product.wizard.partials.category-branch', ['nodes' => $root['children'], 'trail' => [$root['name']], 'root' => $root])
                        </div>
                    </details>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <x-theme::button variant="ghost" :href="$product ? frontRoute('seller.listing.step', [$product->id, 'details']) : frontRoute('seller.product.index')">{{ trans('product::seller.common.cancel') }}</x-theme::button>
            <x-theme::button x-bind:disabled="! selectedId">{{ trans('product::seller.common.continue') }} <x-theme::icon name="arrow-right" size="size-4" /></x-theme::button>
        </div>
    </form>
@endsection
