@extends('theme::layouts.seller.master')

@section('title', trans('logistics::pickup_batch.seller.title'))

@section('page_actions')
    <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.order.index', [['tab' => 'ready', 'sub' => 'awaiting_pickup']])">
        <x-theme::icon name="package" size="size-4" /> {{ trans('logistics::pickup_batch.seller.packed_orders') }}
    </x-theme::button>
@endsection

@section('content')
    <div class="space-y-4">
        <p class="text-sm text-muted">{{ trans('logistics::pickup_batch.seller.hint') }}</p>

        @if ($batches->isEmpty())
            <x-theme::empty-state icon="truck" :title="trans('logistics::pickup_batch.seller.empty_title')" :text="trans('logistics::pickup_batch.seller.empty_text')" />
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ trans('logistics::pickup_batch.columns.manifest_no') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('logistics::pickup_batch.columns.pickup_date') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('logistics::pickup_batch.columns.carrier_name') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('logistics::pickup_batch.columns.shipments_count') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('logistics::pickup_batch.columns.status') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ trans('logistics::pickup_batch.seller.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($batches as $batch)
                            <tr>
                                <td class="px-4 py-3 font-mono font-semibold text-ink">{{ $batch->manifest_no }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $batch->pickup_date?->format('D, d M Y') }}</td>
                                <td class="px-4 py-3">{{ $batch->carrier_name ?? '—' }}<span class="block text-xs text-muted">{{ $batch->warehouse?->name }}</span></td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $batch->shipments_count }}</td>
                                <td class="px-4 py-3">
                                    <x-theme::chip :variant="match ($batch->status) { 'picked_up' => 'success', 'partially_picked' => 'deal', 'open' => 'danger', default => 'accent' }">
                                        {{ trans('logistics::pickup_batch.statuses.'.$batch->status) }}
                                    </x-theme::chip>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ frontRoute('seller.manifest.download', [$batch->id]) }}" class="inline-flex items-center gap-1 font-medium text-accent hover:underline">
                                        <x-theme::icon name="download" size="size-4" /> {{ trans('logistics::pickup_batch.seller.print') }}
                                    </a>
                                    @if ($batch->manifest_url)
                                        <a href="{{ $batch->manifest_url }}" target="_blank" rel="noopener" class="ml-3 text-sm text-ink-soft hover:underline">{{ trans('logistics::pickup_batch.seller.courier_manifest') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $batches->links() }}</div>
        @endif
    </div>
@endsection
