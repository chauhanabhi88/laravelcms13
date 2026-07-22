<div class="card dash-card">
  <div class="card-body">
    <div class="dash-card__head">
      <h4 class="card-title mb-0">{{ trans("dashboard::dashboard.titles.recent_customers") }}</h4>
      @can('admin.customer.index')
      <a href="{{ route('admin.customer.index', updateUrlParams()) }}" class="dash-card__link">
        {{ trans("dashboard::dashboard.labels.view_all") }} <i class="mdi mdi-chevron-right"></i>
      </a>
      @endcan
    </div>
    <div class="table-responsive">
      <table class="table dash-table">
        <thead>
          <tr>
            <th>{{ trans("dashboard::dashboard.labels.name") }}</th>
            <th>{{ trans("dashboard::dashboard.labels.email") }}</th>
            <th>{{ trans("dashboard::dashboard.labels.status") }}</th>
            <th>{{ trans("dashboard::dashboard.labels.registered") }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentCustomers as $customer)
          <tr>
            <td>
              @can('admin.customer.edit')
              <a href="{{ route('admin.customer.edit', updateUrlParams(['id' => $customer->id])) }}">{{ trim($customer->first_name . ' ' . $customer->last_name) }}</a>
              @else
              {{ trim($customer->first_name . ' ' . $customer->last_name) }}
              @endcan
            </td>
            <td>{{ $customer->email }}</td>
            <td>
              @if($customer->status == config('core.enabled'))
              <span class="badge badge-success">{{ trans('core::core.options.status.enable') }}</span>
              @else
              <span class="badge badge-danger">{{ trans('core::core.options.status.disable') }}</span>
              @endif
            </td>
            <td>{{ optional($customer->created_at)->diffForHumans() }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="dash-empty">{{ trans("dashboard::dashboard.messages.no_records") }}</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
