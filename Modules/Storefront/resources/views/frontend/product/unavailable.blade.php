{{--
    404 for a missing or hidden product: the storefront error page plus popular products from
    the same category, so the buyer keeps shopping.
--}}
@extends('pages::errors.front.error')

@section('content')
    @parent
    @if ($cards !== [])
        <section class="container-page pb-16" aria-labelledby="pdp-unavailable-suggestions">
            <h2 id="pdp-unavailable-suggestions" class="text-lg leading-[26px] font-bold text-ink sm:text-[22px] sm:leading-[30px]">
                {{ trans('storefront::product.unavailable.suggestions') }}
            </h2>
            <div class="mt-4">
                @include('storefront::frontend.product.partials.similar-cards', ['cards' => $cards])
            </div>
        </section>
    @endif
@endsection
