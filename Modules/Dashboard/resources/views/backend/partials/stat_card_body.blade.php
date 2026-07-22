<div class="card-body">
  <div class="dash-stat__icon">
    <i class="{{ $card['icon'] }}"></i>
  </div>
  <div class="dash-stat__body">
    <h5 class="dash-stat__label">{{ $card['label'] }}</h5>
    <h2 class="dash-stat__count">{{ number_format($card['count']) }}</h2>
    <span class="dash-stat__meta">
      {{ number_format($card['today']) }}
      {{ $card['today_label'] ?? trans('dashboard::dashboard.labels.today') }}
    </span>
  </div>
</div>
