{{-- Label batch PDF: per seller order its packing slip, then its tax invoice when included. $documents: list<array{slip: array, invoice: array|null}>. --}}
@extends('order::pdf.layout')

@section('title', trans('order::fulfilment.pdf.packing_slips'))

@section('content')
    @foreach ($documents as $document)
        @include('order::pdf.partials.packing-slip', ['slip' => $document['slip']])

        @if ($document['invoice'])
            <div class="page-break"></div>
            @include('order::pdf.partials.tax-invoice', ['invoice' => $document['invoice']])
        @endif

        @unless ($loop->last)
            <div class="page-break"></div>
        @endunless
    @endforeach
@endsection
