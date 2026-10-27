@extends('theme::layouts.seller.master')
{{--
    Listing wizard frame: stepper (saved products can jump between steps), category line and
    status notices. Steps: @extends('product::seller.product.wizard.layout'), @section('step').
--}}

@section('title', $product ? trans('product::seller.wizard.title_edit') : trans('product::seller.wizard.title_new'))

@section('page_actions')
    @if ($product)
        <x-theme::chip :variant="$product->status->chip()">{{ $product->status->label() }}</x-theme::chip>
    @endif
@endsection

@section('content')
    @php
        $steps = \Modules\Product\Support\Listing\ListingWizard::steps();
        $current = \Modules\Product\Support\Listing\ListingWizard::position($step);
    @endphp

    <div class="space-y-5">
        <x-theme::stepper :steps="\Modules\Product\Support\Listing\ListingWizard::labels()" :current="$current" />

        @if ($product)
            <nav class="scrollbar-none -mt-2 flex gap-1 overflow-x-auto text-sm" aria-label="{{ trans('product::seller.wizard.title_edit') }}">
                @foreach (\Modules\Product\Support\Listing\ListingWizard::PRODUCT_STEPS as $item)
                    <a href="{{ frontRoute('seller.listing.step', [$product->id, $item]) }}"
                        @class(['rounded-chip px-3 py-1 whitespace-nowrap transition', 'bg-accent-soft font-medium text-accent' => $item === $step, 'text-muted hover:bg-surface-alt hover:text-ink' => $item !== $step])
                        @if ($item === $step) aria-current="page" @endif>{{ trans('product::seller.wizard.steps.'.$item) }}</a>
                @endforeach
            </nav>
        @endif

        @isset($categoryLabel)
            <p class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                <x-theme::icon name="tag" size="size-4" class="text-muted" />
                {{ trans('product::seller.wizard.category_line', ['category' => $categoryLabel]) }}
                @if ($categoryChangeable ?? false)
                    <a href="{{ $product ? frontRoute('seller.listing.create', [['product' => $product->id]]) : frontRoute('seller.listing.create') }}" class="font-medium text-accent hover:text-accent-hover">{{ trans('product::seller.wizard.change') }}</a>
                @endif
            </p>
        @endisset

        @if ($product)
            @if ($product->status === \Modules\Product\Enums\ProductStatus::Rejected && $product->qc_notes)
                <x-theme::alert type="error">{{ trans('product::seller.wizard.rejected_notice', ['reason' => $product->qc_notes]) }}</x-theme::alert>
            @elseif ($product->isPublished())
                <x-theme::alert type="info">{{ trans('product::seller.wizard.published_notice') }}</x-theme::alert>
                @if ($product->hasPendingChanges())
                    <x-theme::alert type="warning">{{ trans('product::seller.wizard.pending_notice') }}</x-theme::alert>
                @endif
            @endif
        @endif

        @yield('step')
    </div>
@endsection

@section('scripts')
    @yield('step_scripts')
@endsection
