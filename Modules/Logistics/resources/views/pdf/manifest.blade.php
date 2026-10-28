{{-- Pickup manifest with the handover signature table (ManifestDocument). --}}
@extends('order::pdf.layout')

@section('title', trans('logistics::pickup_batch.pdf.title').' '.$batch->manifest_no)

@section('content')
    <table>
        <tr>
            <td style="width: 60%">
                <h1>{{ trans('logistics::pickup_batch.pdf.title') }}</h1>
                <div>{{ trans('logistics::pickup_batch.pdf.manifest_no') }}: <span class="strong mono">{{ $batch->manifest_no }}</span></div>
                <div>{{ trans('logistics::pickup_batch.pdf.pickup_date') }}: <span class="strong">{{ $batch->pickup_date?->format('D, d M Y') }}</span></div>
                <div>{{ trans('logistics::pickup_batch.pdf.courier') }}: {{ $batch->carrier_name ?? '-' }}</div>
                @if ($batch->pickup_reference)
                    <div class="muted">{{ trans('logistics::pickup_batch.pdf.pickup_reference') }}: {{ $batch->pickup_reference }}</div>
                @endif
            </td>
            <td class="box" style="width: 40%">
                <h2>{{ trans('logistics::pickup_batch.pdf.pickup_from') }}</h2>
                <div class="strong">{{ $seller?->displayName() }}</div>
                @if ($warehouse)
                    <div>{{ $warehouse->fullAddress() }}</div>
                    <div>{{ $warehouse->contact_name }} · {{ $warehouse->contact_phone }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="spacer"></div>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th>{{ trans('logistics::pickup_batch.pdf.awb') }}</th>
                <th>{{ trans('logistics::pickup_batch.pdf.sub_order') }}</th>
                <th class="right">{{ trans('logistics::pickup_batch.pdf.items') }}</th>
                <th class="right">{{ trans('logistics::pickup_batch.pdf.weight') }}</th>
                <th>{{ trans('logistics::pickup_batch.pdf.payment') }}</th>
                <th class="right">{{ trans('logistics::pickup_batch.pdf.cod_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="mono strong">{{ $row['awb'] }}</td>
                    <td class="mono">{{ $row['sub_order_no'] }}</td>
                    <td class="right">{{ $row['items'] }}</td>
                    <td class="right">{{ $row['weight_kg'] }}</td>
                    <td>{{ trans('order::fulfilment.payment.'.$row['payment']) }}</td>
                    <td class="right">{{ $row['cod_amount']?->format(true) ?? '-' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6" class="right strong">{{ trans('logistics::pickup_batch.pdf.totals', ['count' => count($rows)]) }}</td>
                <td class="right strong">{{ $codTotal->format(true) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="spacer"></div>
    <div class="spacer"></div>

    <h2>{{ trans('logistics::pickup_batch.pdf.handover') }}</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 50%">{{ trans('logistics::pickup_batch.pdf.handed_over_by') }}</th>
                <th style="width: 50%">{{ trans('logistics::pickup_batch.pdf.received_by') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div>{{ trans('logistics::pickup_batch.pdf.name') }}:</div>
                    <div class="signature"></div>
                    <div>{{ trans('logistics::pickup_batch.pdf.signature_date') }}</div>
                </td>
                <td>
                    <div>{{ trans('logistics::pickup_batch.pdf.name') }}:</div>
                    <div class="signature"></div>
                    <div>{{ trans('logistics::pickup_batch.pdf.signature_date') }}</div>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="spacer"></div>
    <div class="muted">{{ trans('logistics::pickup_batch.pdf.packages_received', ['count' => count($rows)]) }}</div>
@endsection
