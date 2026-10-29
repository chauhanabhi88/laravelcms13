@php
    $langPath = 'review::product_review.columns';
@endphp
{{ formStart(null, 'POST', 'admin.product_review.filters', updateUrlParams(), ['id' => 'search_frm']) }}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<div class="card-header">
    @include('core::partials.pagination')
</div>
<div class="card-body table-responsive">
    <table class="table table-bordered table-striped table-hover text-nowrap">
        <thead>
            <tr class="data-heading">
                @include('core::partials.sorting', ['displayMassDeleteCheckbox' => false])
                <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($collection as $review)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            if ($column['checkbox_checked'] == 0) {
                                continue;
                            }
                            $columnCode = $column['code'];
                        @endphp
                        @if ($columnCode == 'product')
                            <td class="text-truncate" style="max-width: 220px;">{{ $review->orderItem?->name }}</td>
                        @elseif ($columnCode == 'customer')
                            <td>{{ $review->customer?->displayName() ?? $review->author_name }}</td>
                        @elseif ($columnCode == 'seller')
                            <td>{{ $review->seller?->displayName() }}</td>
                        @elseif ($columnCode == 'rating')
                            <td>{{ $review->rating }} ★</td>
                        @elseif ($columnCode == 'title')
                            <td class="text-truncate" style="max-width: 220px;">{{ $review->title ?? \Illuminate\Support\Str::limit((string) $review->body, 60) }}</td>
                        @elseif ($columnCode == 'status')
                            <td>
                                <span class="badge badge-{{ \Modules\Review\Support\ReviewOptions::badge($review->status) }}">{{ $statuses[$review->status] ?? $review->status }}</span>
                                @if ($review->isPublished() && $review->report_count >= $reportThreshold)
                                    <span class="badge badge-danger">{{ trans('review::product_review.labels.reported_badge', ['count' => $review->report_count]) }}</span>
                                @endif
                            </td>
                        @elseif ($columnCode == 'moderation_flags')
                            <td>
                                @forelse ($review->flags() as $flag)
                                    <span class="badge badge-warning">{{ $flags[$flag] ?? $flag }}</span>
                                @empty
                                    -
                                @endforelse
                            </td>
                        @elseif ($columnCode == 'has_images')
                            <td>{{ $review->hasImages() ? trans('review::product_review.labels.yes') : trans('review::product_review.labels.no') }}</td>
                        @elseif (in_array($columnCode, ['created_at', 'published_at'], true))
                            <td>{{ $review->$columnCode ? getFormatedDate($review->$columnCode, getGridDateFormat()) : '-' }}</td>
                        @else
                            <td>{{ $review->$columnCode ?? '-' }}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.product_review.show')
                            <a href="{{ route('admin.product_review.show', updateUrlParams([$review->id])) }}" class="btn">
                                <span data-placement="left" data-toggle="tooltip" title="{{ trans('review::product_review.labels.show') }}"><i class="fas fa-eye"></i></span>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td align="center" colspan="{{ count($columns) + 1 }}">{{ trans('core::core.messages.no_records') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}
