{{-- Old (live) vs new (pending) values of a published product. $pendingDiff. --}}
@php
    $none = trans('product::product.labels.none');
@endphp
<div class="card card-warning card-outline mb-4">
    <div class="card-header"><strong>{{ trans('product::product.titles.pending_changes') }}</strong></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th style="width: 18%"></th>
                    <th style="width: 41%">{{ trans('product::product.labels.old') }}</th>
                    <th style="width: 41%">{{ trans('product::product.labels.new') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pendingDiff as $row)
                    <tr>
                        <th class="font-weight-normal text-muted">{{ $row['label'] }}</th>
                        @if ($row['type'] === 'images')
                            @foreach (['old', 'new'] as $side)
                                <td>
                                    <div class="d-flex flex-wrap">
                                        @forelse ($row[$side] as $image)
                                            <figure class="mr-2 mb-2 text-center" style="width: 80px">
                                                <img src="{{ $image['url'] }}" alt="" class="img-thumbnail" style="width: 80px; height: 106px; object-fit: contain">
                                                @if ($image['option'])
                                                    <figcaption class="small text-muted">{{ $image['option'] }}</figcaption>
                                                @endif
                                            </figure>
                                        @empty
                                            <span class="text-muted">{{ $none }}</span>
                                        @endforelse
                                    </div>
                                </td>
                            @endforeach
                        @elseif ($row['type'] === 'variants')
                            <td class="text-muted">{{ $none }}</td>
                            <td>
                                <table class="table table-sm table-bordered mb-0 small">
                                    @foreach ($row['new'] as $variant)
                                        <tr>
                                            <td>{{ $variant['options'] ?: $none }}</td>
                                            <td>{{ $variant['seller_sku'] }}</td>
                                            <td class="text-right"><s>{{ $variant['mrp'] }}</s> {{ $variant['price'] }}</td>
                                            <td class="text-right">{{ $variant['stock'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        @elseif ($row['type'] === 'specs')
                            @foreach (['old', 'new'] as $side)
                                <td class="small">
                                    @forelse ($row[$side] as $label => $value)
                                        <div class="{{ ($row[$side === 'old' ? 'new' : 'old'][$label] ?? null) !== $value ? 'font-weight-bold' : '' }}">{{ $label }}: {{ $value }}</div>
                                    @empty
                                        <span class="text-muted">{{ $none }}</span>
                                    @endforelse
                                </td>
                            @endforeach
                        @elseif ($row['type'] === 'list')
                            @foreach (['old', 'new'] as $side)
                                <td class="small">
                                    @if (! empty($row[$side]))
                                        <ul class="mb-0 pl-3">
                                            @foreach ($row[$side] as $line)
                                                <li>{{ $line }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">{{ $none }}</span>
                                    @endif
                                </td>
                            @endforeach
                        @else
                            <td class="small" style="white-space: pre-line">@if ($row['old'] !== null && $row['old'] !== ''){{ $row['old'] }}@else<span class="text-muted">{{ $none }}</span>@endif</td>
                            <td class="small bg-light" style="white-space: pre-line">@if ($row['new'] !== null && $row['new'] !== ''){{ $row['new'] }}@else<span class="text-muted">{{ trans('product::product.labels.removed') }}</span>@endif</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
