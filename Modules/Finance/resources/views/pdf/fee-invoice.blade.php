{{-- Platform → seller monthly fee invoice ($invoice = TaxDocumentService::feeInvoiceView()): commission and platform fees with GST. --}}
@extends('order::pdf.layout')

@section('title', trans('finance::tax_document.pdf.fee_invoice').' '.$invoice['number'])

@section('content')
    @php
        $document = $invoice['document'];
    @endphp
    <table>
        <tr>
            <td style="width: 60%">
                <h1>{{ trans('finance::tax_document.pdf.fee_invoice') }}</h1>
                <div class="strong">{{ $invoice['supplier']['legal_name'] }}</div>
                <div>{{ $invoice['supplier']['address'] }}</div>
                @if ($invoice['supplier']['gstin'])
                    <div>{{ trans('order::fulfilment.pdf.gstin') }}: <span class="strong mono">{{ $invoice['supplier']['gstin'] }}</span></div>
                @endif
                @if ($invoice['supplier']['state'])
                    <div>{{ trans('order::fulfilment.pdf.state') }}: {{ $invoice['supplier']['state'] }}</div>
                @endif
            </td>
            <td class="right" style="width: 40%">
                <div>{{ trans('order::fulfilment.pdf.invoice_no') }}: <span class="strong mono">{{ $invoice['number'] }}</span></div>
                <div>{{ trans('order::fulfilment.pdf.invoice_date') }}: {{ $invoice['date']?->format('d M Y') }}</div>
                <div>{{ trans('finance::tax_document.pdf.period') }}: {{ $invoice['period'] }}</div>
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    <div class="box">
        <h2>{{ trans('order::fulfilment.pdf.bill_to') }}</h2>
        <div class="strong">{{ $invoice['recipient']['legal_name'] }} ({{ $invoice['recipient']['code'] }})</div>
        @if ($invoice['recipient']['gstin'])
            <div>{{ trans('order::fulfilment.pdf.gstin') }}: <span class="mono">{{ $invoice['recipient']['gstin'] }}</span></div>
        @endif
        <div>{{ trans('order::fulfilment.pdf.place_of_supply') }}: {{ $invoice['recipient']['state'] ?? '-' }}</div>
    </div>

    <div class="spacer"></div>

    <table class="grid">
        <thead>
            <tr>
                <th>{{ trans('finance::tax_document.pdf.service') }}</th>
                <th>{{ trans('finance::tax_document.pdf.sac') }}</th>
                <th class="right">{{ trans('order::fulfilment.pdf.taxable_value') }}</th>
                <th class="right">{{ trans('finance::tax_document.pdf.gst', ['rate' => $invoice['rate']]) }}</th>
                <th class="right">{{ trans('order::fulfilment.pdf.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice['lines'] as $line)
                @php
                    $taxable = \Modules\Core\Support\Money::of($line['taxable']);
                    $tax = \Modules\Core\Support\Money::of($line['tax']);
                @endphp
                <tr>
                    <td>{{ trans('finance::finance.ledger_types.'.$line['code']) }}</td>
                    <td class="mono">998599</td>
                    <td class="right">{{ $taxable->format(true) }}</td>
                    <td class="right">{{ $tax->format(true) }}</td>
                    <td class="right">{{ $taxable->plus($tax)->format(true) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" class="right strong">{{ trans('order::fulfilment.pdf.totals') }}</td>
                <td class="right strong">{{ $document->taxable_value->format(true) }}</td>
                <td class="right strong">
                    @if ($invoice['interState'])
                        {{ trans('order::fulfilment.pdf.igst') }} {{ $document->igst->format(true) }}
                    @else
                        {{ trans('order::fulfilment.pdf.cgst') }} {{ $document->cgst->format(true) }}<br>{{ trans('order::fulfilment.pdf.sgst') }} {{ $document->sgst->format(true) }}
                    @endif
                </td>
                <td class="right strong">{{ $document->total->format(true) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="spacer"></div>
    <div>{{ trans('order::fulfilment.pdf.in_words') }}: <span class="strong">{{ $invoice['inWords'] }}</span></div>
    <div>{{ trans('finance::tax_document.pdf.settled_from_payouts') }}</div>
    <div class="spacer"></div>
    <table>
        <tr>
            <td style="width: 55%" class="muted">{{ trans('finance::tax_document.pdf.computer_generated') }}</td>
            <td style="width: 45%" class="right">
                <div>{{ trans('order::fulfilment.pdf.for_supplier', ['name' => $invoice['supplier']['legal_name']]) }}</div>
                <div class="signature"></div>
                <div>{{ trans('order::fulfilment.pdf.authorised_signatory') }}</div>
            </td>
        </tr>
    </table>
@endsection
