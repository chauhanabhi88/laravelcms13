@extends('finance::backend.layouts.grid')

@section('above_grid')
    <div class="row">
        @can('admin.cod_remittance.upload')
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ trans('finance::cod_remittance.upload_title') }}</strong></div>
                    <div class="card-body">
                        <p class="text-muted small">{{ trans('finance::cod_remittance.upload_hint') }}</p>
                        <form method="POST" action="{{ route('admin.cod_remittance.upload', updateUrlParams()) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <input type="file" name="file" accept=".csv,text/csv" class="form-control-file @error('file') is-invalid @enderror" required>
                                @error('file')<label class="error">{{ $message }}</label>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary">{{ trans('finance::cod_remittance.buttons.upload') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ trans('finance::cod_remittance.overdue_title', ['count' => $overdueCount, 'days' => config('finance.cod_remittance_overdue_days', 10)]) }}</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($overdue as $shipment)
                                <tr data-overdue="{{ $shipment->awb }}">
                                    <td class="text-monospace">{{ $shipment->awb }}</td>
                                    <td>{{ $shipment->sellerOrder?->sub_order_no }}</td>
                                    <td class="text-right">{{ $shipment->cod_amount->format(true) }}</td>
                                    <td>{{ $shipment->delivered_at ? getFormatedDate($shipment->delivered_at, getGridDateFormat()) : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-muted p-3">{{ trans('finance::cod_remittance.overdue_none') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
