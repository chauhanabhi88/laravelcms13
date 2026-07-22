<div class="row dash-stats">
  @forelse($statCards as $card)
  <div class="col-xl col-lg-4 col-md-6 col-sm-6 grid-margin stretch-card">
    {{-- the route name doubles as the permission, same as the "can:" middleware on that route --}}
    @can($card['route'])
    <a class="card dash-stat dash-stat--{{ $card['color'] }}" href="{{ route($card['route'], updateUrlParams()) }}">
      @include('dashboard::backend.partials.stat_card_body', ['card' => $card])
    </a>
    @else
    <div class="card dash-stat dash-stat--{{ $card['color'] }}">
      @include('dashboard::backend.partials.stat_card_body', ['card' => $card])
    </div>
    @endcan
  </div>
  @empty
  <div class="col-12 grid-margin">
    <div class="card dash-card">
      <div class="card-body">
        <p class="dash-empty mb-0">{{ trans('dashboard::dashboard.messages.no_records') }}</p>
      </div>
    </div>
  </div>
  @endforelse
</div>
