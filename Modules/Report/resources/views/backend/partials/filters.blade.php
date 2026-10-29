{{-- Filter bar (plan M19 §7): period preset or custom dates, comparison, and one dimension filter. --}}
<form method="GET" action="{{ route('admin.report.'.$report, updateUrlParams()) }}" class="card rpt-card rpt-filters grid-margin" data-report-filters>
    <div class="card-body form-row align-items-end">
        <div class="form-group col-sm-6 col-lg-2">
            <label for="rpt-preset">{{ trans('report::report.filters.period') }}</label>
            <select id="rpt-preset" name="preset" class="form-control" data-report-preset>
                @foreach (config('report.presets') as $preset)
                    <option value="{{ $preset }}" @selected($range->preset === $preset)>{{ trans('report::report.presets.'.$preset) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-sm-3 col-lg-2" data-report-custom>
            <label for="rpt-from">{{ trans('report::report.filters.from') }}</label>
            <input type="date" id="rpt-from" name="from" value="{{ $range->from->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control">
        </div>
        <div class="form-group col-sm-3 col-lg-2" data-report-custom>
            <label for="rpt-to">{{ trans('report::report.filters.to') }}</label>
            <input type="date" id="rpt-to" name="to" value="{{ $range->to->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control">
        </div>
        <div class="form-group col-sm-6 col-lg-2">
            <label for="rpt-compare">{{ trans('report::report.filters.compare') }}</label>
            <select id="rpt-compare" name="compare" class="form-control">
                @foreach (config('report.compare_modes') as $mode)
                    <option value="{{ $mode }}" @selected($range->compareMode === $mode)>{{ trans('report::report.compare_modes.'.$mode) }}</option>
                @endforeach
            </select>
        </div>

        @if (in_array('seller', $dimensions, true))
            <div class="form-group col-sm-6 col-lg-2">
                <label for="rpt-seller">{{ trans('report::report.filters.seller') }}</label>
                <select id="rpt-seller" name="seller_id" class="form-control">
                    <option value="">{{ trans('report::report.filters.all') }}</option>
                    @foreach ($filterOptions['seller'] as $id => $name)
                        <option value="{{ $id }}" @selected($filters->sellerId === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if (in_array('category', $dimensions, true))
            <div class="form-group col-sm-6 col-lg-2">
                <label for="rpt-category">{{ trans('report::report.filters.category') }}</label>
                <select id="rpt-category" name="category_id" class="form-control">
                    <option value="">{{ trans('report::report.filters.all') }}</option>
                    @foreach ($filterOptions['category'] as $id => $name)
                        <option value="{{ $id }}" @selected($filters->categoryId === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if (in_array('state', $dimensions, true))
            <div class="form-group col-sm-6 col-lg-2">
                <label for="rpt-state">{{ trans('report::report.filters.state') }}</label>
                <select id="rpt-state" name="state_id" class="form-control">
                    <option value="">{{ trans('report::report.filters.all') }}</option>
                    @foreach ($filterOptions['state'] as $id => $name)
                        <option value="{{ $id }}" @selected($filters->stateId === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if (in_array('payment', $dimensions, true))
            <div class="form-group col-sm-6 col-lg-2">
                <label for="rpt-payment">{{ trans('report::report.filters.payment') }}</label>
                <select id="rpt-payment" name="payment" class="form-control">
                    <option value="">{{ trans('report::report.filters.all') }}</option>
                    @foreach (\Modules\Report\Support\Grain::PAYMENTS as $payment)
                        <option value="{{ $payment }}" @selected($filters->payment === $payment)>{{ trans('report::report.payments.'.$payment) }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if (! empty($sort))
            <input type="hidden" name="sort" value="{{ $sort }}">
        @endif

        <div class="form-group col-sm-12 col-lg-auto rpt-filters__actions">
            <button type="submit" class="btn btn-primary">{{ trans('report::report.filters.apply') }}</button>
            <a href="{{ route('admin.report.'.$report, updateUrlParams()) }}" class="btn btn-outline-secondary">{{ trans('report::report.filters.reset') }}</a>
        </div>
        @if (count($dimensions) > 1)
            <p class="col-12 rpt-muted mb-0 small">{{ trans('report::report.filters.one_at_a_time') }}</p>
        @endif
    </div>
</form>
