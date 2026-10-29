@extends('finance::backend.layouts.grid')

@section('header_actions')
    @can('admin.ledger.export')
        <a href="{{ route('admin.ledger.export', updateUrlParams()) }}" class="btn btn-secondary btn-fw">{{ trans('finance::finance.buttons.export_csv') }}</a>
    @endcan
@endsection

@section('above_grid')
    <div class="row">
        @can('admin.ledger.adjust')
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ trans('finance::ledger.adjust.title') }}</strong></div>
                    <div class="card-body">
                        <p class="text-muted small">{{ trans('finance::ledger.adjust.hint') }}</p>
                        <form method="POST" action="{{ route('admin.ledger.adjust', updateUrlParams()) }}">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="seller_code">{{ trans('finance::ledger.adjust.seller_code') }}</label>
                                    <input type="text" id="seller_code" name="seller_code" value="{{ old('seller_code') }}" class="form-control @error('seller_code') is-invalid @enderror" maxlength="12" required>
                                    @error('seller_code')<label class="error">{{ $message }}</label>@enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="direction">{{ trans('finance::ledger.adjust.direction') }}</label>
                                    <select id="direction" name="direction" class="custom-select">
                                        @foreach ($directionOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('direction') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="amount">{{ trans('finance::ledger.adjust.amount') }}</label>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
                                @error('amount')<label class="error">{{ $message }}</label>@enderror
                            </div>
                            <div class="form-group">
                                <label for="reason">{{ trans('finance::ledger.adjust.reason') }}</label>
                                <textarea id="reason" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" maxlength="500" required>{{ old('reason') }}</textarea>
                                @error('reason')<label class="error">{{ $message }}</label>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary">{{ trans('finance::ledger.adjust.submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><strong>{{ trans('finance::ledger.adjust.pending') }}</strong></div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @forelse ($pendingAdjustments as $adjustment)
                                <tr data-adjustment="{{ $adjustment->id }}">
                                    <td>{{ $adjustment->seller?->code }}<br><small class="text-muted">{{ $adjustment->requester?->name }}</small></td>
                                    <td class="{{ $adjustment->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $adjustment->direction === 'credit' ? '+' : '-' }}{{ $adjustment->amount->format(true) }}</td>
                                    <td class="text-wrap">{{ $adjustment->reason }}</td>
                                    <td class="text-nowrap">
                                        @can('admin.ledger.approve_adjustment')
                                            <form method="POST" action="{{ route('admin.ledger.approve_adjustment', updateUrlParams([$adjustment->id])) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">{{ trans('finance::ledger.adjust.approve') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.ledger.reject_adjustment', updateUrlParams([$adjustment->id])) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ trans('finance::ledger.adjust.reject') }}</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-muted p-3">{{ trans('finance::ledger.adjust.none') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
