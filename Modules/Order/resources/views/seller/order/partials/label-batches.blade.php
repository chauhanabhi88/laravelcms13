{{-- Recent label batches: queued ones poll seller.label.status until the file is ready. --}}
@if ($labelBatches->isNotEmpty())
    <section class="rounded-card border border-line bg-surface p-4 shadow-card" aria-labelledby="label-batches-heading">
        <h2 id="label-batches-heading" class="text-sm font-semibold text-ink">{{ trans('order::fulfilment.labels.recent') }}</h2>
        <ul class="mt-2 divide-y divide-line text-sm">
            @foreach ($labelBatches as $batch)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2"
                    x-data="{
                        status: @js($batch->status),
                        url: @js($batch->isReady() ? frontRoute('seller.label.download', [$batch->id]) : null),
                        error: @js($batch->error),
                        init() {
                            if (this.status !== 'queued' && this.status !== 'processing') return;
                            const timer = setInterval(async () => {
                                try {
                                    const response = await fetch(@js(frontRoute('seller.label.status', [$batch->id])), { headers: { 'Accept': 'application/json' } });
                                    const data = await response.json();
                                    this.status = data.status; this.url = data.url; this.error = data.error;
                                    if (data.ready || data.status === 'failed') clearInterval(timer);
                                } catch (e) { clearInterval(timer) }
                            }, 3000);
                        },
                    }">
                    <span>
                        <span class="font-medium text-ink">#{{ $batch->id }}</span>
                        <span class="text-muted">· {{ trans_choice('order::fulfilment.labels.orders', $batch->orders_count, ['count' => $batch->orders_count]) }} · {{ $batch->created_at?->format('d M, g:i A') }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span x-show="status === 'queued' || status === 'processing'" @if (! $batch->isPending()) x-cloak @endif>
                            <x-theme::chip icon="clock">{{ trans('order::fulfilment.labels.status.processing') }}</x-theme::chip>
                        </span>
                        <span x-show="status === 'failed'" @if ($batch->status !== 'failed') x-cloak @endif>
                            <x-theme::chip variant="danger" x-bind:title="error">{{ trans('order::fulfilment.labels.status.failed') }}</x-theme::chip>
                        </span>
                        <a x-show="url" x-bind:href="url" @if ($batch->isReady()) href="{{ frontRoute('seller.label.download', [$batch->id]) }}" @else x-cloak @endif
                            class="inline-flex items-center gap-1 font-medium text-accent hover:underline">
                            <x-theme::icon name="download" size="size-4" /> {{ trans('order::fulfilment.labels.download') }}
                        </a>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
