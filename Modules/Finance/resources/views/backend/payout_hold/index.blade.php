@extends('finance::backend.layouts.grid')

@section('above_grid')
    <div class="alert alert-info">{{ trans('finance::payout_hold.hint') }}</div>
    @can('admin.payout_hold.store')
        <div class="card mb-3">
            <div class="card-header"><strong>{{ trans('finance::payout_hold.place_title') }}</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.payout_hold.store', updateUrlParams()) }}" class="form-row align-items-end">
                    @csrf
                    <div class="form-group col-md-3">
                        <label for="seller_code">{{ trans('finance::ledger.adjust.seller_code') }}</label>
                        <input type="text" id="seller_code" name="seller_code" value="{{ old('seller_code') }}" class="form-control @error('seller_code') is-invalid @enderror" maxlength="12" required>
                        @error('seller_code')<label class="error">{{ $message }}</label>@enderror
                    </div>
                    <div class="form-group col-md-7">
                        <label for="reason">{{ trans('finance::payout_hold.reason') }}</label>
                        <input type="text" id="reason" name="reason" value="{{ old('reason') }}" class="form-control @error('reason') is-invalid @enderror" maxlength="500" required>
                        @error('reason')<label class="error">{{ $message }}</label>@enderror
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-warning btn-block">{{ trans('finance::payout_hold.buttons.place') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection
