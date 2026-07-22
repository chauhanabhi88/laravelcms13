<div class="card dash-card">
  <div class="card-body">
    <div class="dash-card__head">
      <h4 class="card-title mb-0">{{ trans("dashboard::dashboard.titles.recent_enquiries") }}</h4>
      @can('admin.contact.index')
      <a href="{{ route('admin.contact.index', updateUrlParams()) }}" class="dash-card__link">
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
            <th>{{ trans("dashboard::dashboard.labels.received") }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentContacts as $contact)
          <tr>
            <td>
              @can('admin.contact.view')
              <a href="{{ route('admin.contact.view', updateUrlParams(['id' => $contact->id])) }}">{{ $contact->name }}</a>
              @else
              {{ $contact->name }}
              @endcan
            </td>
            <td>{{ $contact->email }}</td>
            <td>{{ optional($contact->created_at)->diffForHumans() }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="dash-empty">{{ trans("dashboard::dashboard.messages.no_records") }}</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
