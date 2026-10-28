{{-- One packing slip ($slip = PackingSlipView::packingSlip()). The courier label carries the AWB barcode; this shows it as text. --}}
<table>
    <tr>
        <td style="width: 60%">
            <h1>{{ trans('order::fulfilment.pdf.packing_slip') }}</h1>
            <div>{{ trans('order::fulfilment.pdf.sub_order') }}: <span class="strong mono">{{ $slip['subOrderNo'] }}</span></div>
            <div class="muted">{{ trans('order::fulfilment.pdf.order') }}: {{ $slip['orderNo'] }} · {{ $slip['orderDate']?->format('d M Y') }}</div>
        </td>
        <td class="right" style="width: 40%">
            <span class="chip">{{ trans('order::fulfilment.payment.'.$slip['paymentMode']) }}</span>
            @if ($slip['awb'])
                <div class="spacer"></div>
                <div class="muted">{{ trans('order::fulfilment.pdf.awb') }}{{ $slip['carrier'] ? ' · '.$slip['carrier'] : '' }}</div>
                <div class="awb mono">{{ $slip['awb'] }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="spacer"></div>

<table>
    <tr>
        <td class="box" style="width: 55%">
            <h2>{{ trans('order::fulfilment.pdf.ship_to') }}</h2>
            @if ($slip['shipTo'])
                <div class="strong">{{ $slip['shipTo']['name'] }}</div>
                @foreach ($slip['shipTo']['lines'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
                <div class="strong">{{ trans('order::fulfilment.pdf.pincode') }}: {{ $slip['shipTo']['pincode'] }}</div>
                @if ($slip['shipTo']['phone'])
                    <div>{{ trans('order::fulfilment.pdf.phone') }}: {{ $slip['shipTo']['phone'] }}</div>
                @endif
            @endif
        </td>
        <td style="width: 3%"></td>
        <td class="box" style="width: 42%">
            <h2>{{ trans('order::fulfilment.pdf.from') }}</h2>
            <div class="strong">{{ $slip['sender']['name'] }}</div>
            @foreach ($slip['sender']['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            @if ($slip['sender']['pincode'])
                <div>{{ trans('order::fulfilment.pdf.pincode') }}: {{ $slip['sender']['pincode'] }}</div>
            @endif
            @if ($slip['sender']['phone'])
                <div>{{ trans('order::fulfilment.pdf.phone') }}: {{ $slip['sender']['phone'] }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="spacer"></div>

<table class="grid">
    <thead>
        <tr>
            <th style="width: 6%">#</th>
            <th>{{ trans('order::fulfilment.pdf.item') }}</th>
            <th style="width: 26%">{{ trans('order::fulfilment.pdf.sku') }}</th>
            <th class="right" style="width: 10%">{{ trans('order::fulfilment.pdf.qty') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($slip['items'] as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item['name'] }}@if ($item['variant'])<br><span class="muted">{{ $item['variant'] }}</span>@endif</td>
                <td class="mono">{{ $item['seller_sku'] ?: $item['sku'] }}</td>
                <td class="right strong">{{ $item['qty'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" class="right strong">{{ trans('order::fulfilment.pdf.total_qty') }}</td>
            <td class="right strong">{{ $slip['totalQty'] }}</td>
        </tr>
    </tbody>
</table>
