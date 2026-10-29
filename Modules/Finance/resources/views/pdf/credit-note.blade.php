{{-- GST credit note for a refunded return ($note = TaxDocumentService::creditNoteView()), referencing the original tax invoice. --}}
@extends('order::pdf.layout')

@section('title', trans('finance::tax_document.pdf.credit_note').' '.$note['number'])

@section('content')
    @php
        $document = $note['document'];
    @endphp
    <table>
        <tr>
            <td style="width: 60%">
                <h1>{{ trans('finance::tax_document.pdf.credit_note') }}</h1>
                <div class="strong">{{ $note['supplier']['legal_name'] }}</div>
                <div>{{ $note['supplier']['address'] }}</div>
                @if ($note['supplier']['gstin'])
                    <div>{{ trans('order::fulfilment.pdf.gstin') }}: <span class="strong mono">{{ $note['supplier']['gstin'] }}</span></div>
                @endif
            </td>
            <td class="right" style="width: 40%">
                <div>{{ trans('finance::tax_document.pdf.note_no') }}: <span class="strong mono">{{ $note['number'] }}</span></div>
                <div>{{ trans('finance::tax_document.pdf.date') }}: {{ $note['date']?->format('d M Y') }}</div>
                <div>{{ trans('finance::tax_document.pdf.against_invoice') }}: <span class="mono">{{ $note['reference'] ?? '-' }}</span></div>
                <div>{{ trans('order::fulfilment.pdf.order') }}: {{ $note['orderNo'] }}</div>
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    @if ($note['buyer'])
        <div class="box">
            <h2>{{ trans('order::fulfilment.pdf.bill_to') }}</h2>
            <div class="strong">{{ $note['buyer']['name'] }}</div>
            @foreach ($note['buyer']['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            <div>{{ $note['buyer']['pincode'] }}</div>
        </div>
        <div class="spacer"></div>
    @endif

    <table class="grid">
        <thead>
            <tr>
                <th>{{ trans('order::fulfilment.pdf.item') }}</th>
                <th>{{ trans('order::fulfilment.pdf.hsn') }}</th>
                <th class="right">{{ trans('order::fulfilment.pdf.qty') }}</th>
                <th class="right">{{ trans('order::fulfilment.pdf.taxable_value') }}</th>
                @if ($note['interState'])
                    <th class="right">{{ trans('order::fulfilment.pdf.igst') }}</th>
                @else
                    <th class="right">{{ trans('order::fulfilment.pdf.cgst') }}</th>
                    <th class="right">{{ trans('order::fulfilment.pdf.sgst') }}</th>
                @endif
                <th class="right">{{ trans('order::fulfilment.pdf.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($note['lines'] as $line)
                <tr>
                    <td>{{ $line['name'] }}@if (! empty($line['variant']))<br><span class="muted">{{ $line['variant'] }}</span>@endif</td>
                    <td class="mono">{{ $line['hsn'] ?? '-' }}</td>
                    <td class="right">{{ $line['qty'] }}</td>
                    <td class="right">{{ \Modules\Core\Support\Money::of($line['taxable'])->format(true) }}</td>
                    @if ($note['interState'])
                        <td class="right">{{ \Modules\Core\Support\Money::of($line['igst'])->format(true) }}</td>
                    @else
                        <td class="right">{{ \Modules\Core\Support\Money::of($line['cgst'])->format(true) }}</td>
                        <td class="right">{{ \Modules\Core\Support\Money::of($line['sgst'])->format(true) }}</td>
                    @endif
                    <td class="right">{{ \Modules\Core\Support\Money::of($line['total'])->format(true) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="right strong">{{ trans('order::fulfilment.pdf.totals') }}</td>
                <td class="right strong">{{ $document->taxable_value->format(true) }}</td>
                @if ($note['interState'])
                    <td class="right strong">{{ $document->igst->format(true) }}</td>
                @else
                    <td class="right strong">{{ $document->cgst->format(true) }}</td>
                    <td class="right strong">{{ $document->sgst->format(true) }}</td>
                @endif
                <td class="right strong">{{ $document->total->format(true) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="spacer"></div>
    <div>{{ trans('order::fulfilment.pdf.in_words') }}: <span class="strong">{{ $note['inWords'] }}</span></div>
    <div class="spacer"></div>
    <table>
        <tr>
            <td style="width: 55%" class="muted">{{ trans('finance::tax_document.pdf.computer_generated') }}</td>
            <td style="width: 45%" class="right">
                <div>{{ trans('order::fulfilment.pdf.for_supplier', ['name' => $note['supplier']['legal_name']]) }}</div>
                <div class="signature"></div>
                <div>{{ trans('order::fulfilment.pdf.authorised_signatory') }}</div>
            </td>
        </tr>
    </table>
@endsection
