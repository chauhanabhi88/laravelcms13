@extends('theme::layouts.frontend.master')

@section('title', trans($isBuyNow ? 'cart::front.buy_now_title' : 'cart::front.title'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    {{--
        Cart page (FR-CRT-002…006). Line forms post normally without JavaScript; with it,
        cartPage() sends them with fetch and swaps in the re-rendered body (resources/js/components/cart.js).
    --}}
    <div class="container-page pt-6 pb-32 lg:pt-8 lg:pb-12" x-data="cartPage()" x-on:submit="submit($event)" x-on:change="changed($event)"
        x-bind:aria-busy="busy" data-cart-view="page">
        @include('cart::frontend.partials.cart-body')
    </div>
@endsection
