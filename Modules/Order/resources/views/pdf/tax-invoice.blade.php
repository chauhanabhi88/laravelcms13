{{-- A single GST tax invoice ($invoice = PackingSlipView::taxInvoice()). --}}
@extends('order::pdf.layout')

@section('title', trans('order::fulfilment.pdf.tax_invoice').' '.($invoice['number'] ?? ''))

@section('content')
    @include('order::pdf.partials.tax-invoice')
@endsection
