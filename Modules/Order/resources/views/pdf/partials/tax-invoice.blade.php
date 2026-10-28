{{-- GST tax invoice (Rule 46) of one seller order ($invoice = PackingSlipView::taxInvoice()). Shared with M17. --}}
<table>
    <tr>
        <td style="width: 60%">
            <h1>{{ trans('order::fulfilment.pdf.tax_invoice') }}</h1>
            <div class="strong">{{ $invoice['supplier']['legal_name'] }}</div>
            @if ($invoice['supplier']['trade_name'] && $invoice['supplier']['trade_name'] !== $invoice['supplier']['legal_name'])
                <div class="muted">{{ trans('order::fulfilment.pdf.trade_name') }}: {{ $invoice['supplier']['trade_name'] }}</div>
            @endif
            <div>{{ $invoice['supplier']['address'] }}</div>
            @if ($invoice['supplier']['gstin'])
                <div>{{ trans('order::fulfilment.pdf.gstin') }}: <span class="strong mono">{{ $invoice['supplier']['gstin'] }}</span></div>
            @elseif ($invoice['supplier']['enrolment_id'])
                <div>{{ trans('order::fulfilment.pdf.enrolment_id') }}: <span class="mono">{{ $invoice['supplier']['enrolment_id'] }}</span></div>
            @endif
            @if ($invoice['supplier']['state'])
                <div>{{ trans('order::fulfilment.pdf.state') }}: {{ $invoice['supplier']['state'] }}</div>
            @endif
        </td>
        <td class="right" style="width: 40%">
            <div>{{ trans('order::fulfilment.pdf.invoice_no') }}: <span class="strong mono">{{ $invoice['number'] ?? '-' }}</span></div>
            <div>{{ trans('order::fulfilment.pdf.invoice_date') }}: {{ $invoice['date']?->format('d M Y') }}</div>
            <div>{{ trans('order::fulfilment.pdf.sub_order') }}: {{ $invoice['subOrderNo'] }}</div>
            <div>{{ trans('order::fulfilment.pdf.order') }}: {{ $invoice['orderNo'] }}</div>
        </td>
    </tr>
</table>

<div class="spacer"></div>

<table>
    <tr>
        <td class="box" style="width: 49%">
            <h2>{{ trans('order::fulfilment.pdf.bill_to') }}</h2>
            @if ($invoice['buyer'])
                <div class="strong">{{ $invoice['buyer']['name'] }}</div>
                @foreach ($invoice['buyer']['lines'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
                <div>{{ $invoice['buyer']['pincode'] }}</div>
            @endif
        </td>
        <td style="width: 2%"></td>
        <td class="box" style="width: 49%">
            <h2>{{ trans('order::fulfilment.pdf.ship_to') }}</h2>
            @if ($invoice['shipTo'])
                <div class="strong">{{ $invoice['shipTo']['name'] }}</div>
                @foreach ($invoice['shipTo']['lines'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
                <div>{{ $invoice['shipTo']['pincode'] }}</div>
            @endif
            <div class="spacer"></div>
            <div>{{ trans('order::fulfilment.pdf.place_of_supply') }}: <span class="strong">{{ $invoice['placeOfSupply'] ?? '-' }}</span></div>
        </td>
    </tr>
</table>

<div class="spacer"></div>

<table class="grid">
    <thead>
        <tr>
            <th>{{ trans('order::fulfilment.pdf.item') }}</th>
            <th>{{ trans('order::fulfilment.pdf.hsn') }}</th>
            <th class="right">{{ trans('order::fulfilment.pdf.qty') }}</th>
            <th class="right">{{ trans('order::fulfilment.pdf.unit_price') }}</th>
            <th class="right">{{ trans('order::fulfilment.pdf.discount') }}</th>
            <th class="right">{{ trans('order::fulfilment.pdf.taxable_value') }}</th>
            @if ($invoice['interState'])
                <th class="right">{{ trans('order::fulfilment.pdf.igst') }}</th>
            @else
                <th class="right">{{ trans('order::fulfilment.pdf.cgst') }}</th>
                <th class="right">{{ trans('order::fulfilment.pdf.sgst') }}</th>
            @endif
            <th class="right">{{ trans('order::fulfilment.pdf.total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice['lines'] as $line)
            <tr>
                <td>{{ $line['name'] }}@if ($line['variant'])<br><span class="muted">{{ $line['variant'] }}</span>@endif</td>
                <td class="mono">{{ $line['hsn'] ?? '-' }}</td>
                <td class="right">{{ $line['qty'] }}</td>
                <td class="right">{{ $line['unit_price']->format(true) }}</td>
                <td class="right">{{ $line['discount']->format(true) }}</td>
                <td class="right">{{ $line['taxable']->format(true) }}</td>
                @if ($invoice['interState'])
                    <td class="right">{{ $line['gst_percent'] }}%<br>{{ $line['igst']->format(true) }}</td>
                @else
                    <td class="right">{{ $line['half_percent'] }}%<br>{{ $line['cgst']->format(true) }}</td>
                    <td class="right">{{ $line['half_percent'] }}%<br>{{ $line['sgst']->format(true) }}</td>
                @endif
                <td class="right">{{ $line['total']->format(true) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" class="right strong">{{ trans('order::fulfilment.pdf.totals') }}</td>
            <td class="right strong">{{ $invoice['totals']['taxable']->format(true) }}</td>
            @if ($invoice['interState'])
                <td class="right strong">{{ $invoice['totals']['igst']->format(true) }}</td>
            @else
                <td class="right strong">{{ $invoice['totals']['cgst']->format(true) }}</td>
                <td class="right strong">{{ $invoice['totals']['sgst']->format(true) }}</td>
            @endif
            <td class="right strong">{{ $invoice['totals']['total']->format(true) }}</td>
        </tr>
    </tbody>
</table>

<div class="spacer"></div>

<div>{{ trans('order::fulfilment.pdf.in_words') }}: <span class="strong">{{ $invoice['inWords'] }}</span></div>
<div>{{ trans('order::fulfilment.pdf.reverse_charge') }}</div>

<div class="spacer"></div>

<table>
    <tr>
        <td style="width: 55%" class="muted">{{ trans('order::fulfilment.pdf.computer_generated') }}</td>
        <td style="width: 45%" class="right">
            <div>{{ trans('order::fulfilment.pdf.for_supplier', ['name' => $invoice['supplier']['legal_name']]) }}</div>
            <div class="signature"></div>
            <div>{{ trans('order::fulfilment.pdf.authorised_signatory') }}</div>
        </td>
    </tr>
</table>
