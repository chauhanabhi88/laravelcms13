@extends('storefront::frontend.listing')

{{-- Search result pages are not indexed; their links are still followed. --}}
@section('listing-meta')
    <meta name="robots" content="noindex, follow">
@endsection
