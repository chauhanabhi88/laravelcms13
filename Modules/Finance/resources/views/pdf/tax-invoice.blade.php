{{-- Seller → buyer GST tax invoice ($invoice = PackingSlipView::taxInvoice()), the same template as the M14 packing slip invoice. --}}
@extends('order::pdf.layout')

@section('title', trans('order::fulfilment.pdf.tax_invoice').' '.($invoice['number'] ?? ''))

@section('content')
    @include('order::pdf.partials.tax-invoice')
@endsection
