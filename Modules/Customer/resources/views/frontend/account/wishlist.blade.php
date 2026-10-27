@extends('customer::frontend.account.layout')

@section('title', trans('customer::customer_front.wishlist.title'))
@section('heading', trans('customer::customer_front.wishlist.title'))
@section('subheading', $page->total() > 0
    ? trans_choice('customer::customer_front.wishlist.count', $page->total(), ['count' => $page->total()])
    : trans('customer::customer_front.wishlist.subtitle'))

@section('account')
    @if ($cards === [])
        <x-theme::empty-state icon="heart" :title="trans('customer::customer_front.wishlist.empty')" :text="trans('customer::customer_front.wishlist.empty_text')">
            @if (\Illuminate\Support\Facades\Route::has('homepage'))
                <x-theme::button :href="frontRoute('homepage')">{{ trans('customer::customer_front.wishlist.browse') }}</x-theme::button>
            @endif
        </x-theme::empty-state>
    @else
        <ul class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($cards as $card)
                <li class="flex flex-col gap-2">
                    <x-theme::product-card :product="$card" />
                    <div class="flex gap-2">
                        <form method="POST" action="{{ frontRoute('customer.wishlist.destroy', [$card['id']]) }}" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <x-theme::button variant="secondary" size="sm" class="w-full">
                                <x-theme::icon name="trash" size="size-4" /> {{ trans('customer::customer_front.wishlist.remove') }}
                            </x-theme::button>
                        </form>
                        @if ($moveToCartUrl)
                            <form method="POST" action="{{ $moveToCartUrl }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $card['id'] }}">
                                <x-theme::button size="sm" class="w-full">
                                    <x-theme::icon name="cart" size="size-4" /> {{ trans('customer::customer_front.wishlist.move_to_cart') }}
                                </x-theme::button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($page->hasPages())
            <div class="mt-8">{{ $page->links() }}</div>
        @endif
    @endif
@endsection
