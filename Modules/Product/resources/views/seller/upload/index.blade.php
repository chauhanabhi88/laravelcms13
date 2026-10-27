@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.uploads.title'))

@section('page_actions')
    <div class="flex gap-2">
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.price_stock.create')">
            <x-theme::icon name="tag" size="size-4" /> {{ trans('product::seller.menu.price_stock') }}
        </x-theme::button>
        <x-theme::button size="sm" :href="frontRoute('seller.upload.create')">
            <x-theme::icon name="upload" size="size-4" /> {{ trans('product::seller.uploads.new') }}
        </x-theme::button>
    </div>
@endsection

@section('content')
    @php
        $pending = $uploads->getCollection()->contains(fn ($upload) => ! $upload->isFinished());
    @endphp
    <div class="space-y-4">
        <nav class="flex gap-1" aria-label="{{ trans('product::seller.uploads.title') }}">
            @foreach (['all' => null, 'new_catalog' => 'new_catalog', 'price_stock' => 'price_stock'] as $label => $value)
                <a href="{{ frontRoute('seller.upload.index', [['type' => $value]]) }}"
                    @class(['rounded-chip px-3 py-1 text-sm transition', 'bg-accent-soft font-medium text-accent' => $type === $value, 'text-muted hover:bg-surface-alt hover:text-ink' => $type !== $value])>{{ trans('product::seller.uploads.types.'.$label) }}</a>
            @endforeach
        </nav>

        @if ($uploads->isEmpty())
            <x-theme::empty-state icon="upload" :title="trans('product::seller.uploads.empty.title')" :text="trans('product::seller.uploads.empty.text')">
                <x-theme::button :href="frontRoute('seller.upload.create')">{{ trans('product::seller.uploads.new') }}</x-theme::button>
            </x-theme::empty-state>
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card"
                @if ($pending) x-data="{ timer: null, init() { this.timer = setTimeout(() => window.location.reload(), 5000) }, destroy() { clearTimeout(this.timer) } }" @endif>
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ trans('product::seller.uploads.columns.id') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('product::seller.uploads.columns.file') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('product::seller.uploads.columns.type') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('product::seller.uploads.columns.status') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('product::seller.uploads.columns.rows') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('product::seller.uploads.columns.uploaded') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('product::seller.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($uploads as $upload)
                            <tr>
                                <td class="px-4 py-3 text-muted tabular-nums">{{ $upload->id }}</td>
                                <td class="max-w-64 px-4 py-3">
                                    <span class="block truncate font-medium text-ink">{{ $upload->original_name }}</span>
                                    @if ($upload->catalog_category_id)
                                        <span class="block truncate text-xs text-muted">{{ $categoryLabels[$upload->catalog_category_id] ?? '' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-soft">{{ trans('product::seller.uploads.types.'.$upload->type) }}</td>
                                <td class="px-4 py-3"><x-theme::chip :variant="$upload->statusChip()">{{ $upload->statusLabel() }}</x-theme::chip></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($upload->isFinished())
                                        <span class="text-success">{{ trans('product::seller.uploads.rows_ok', ['count' => $upload->success_rows]) }}</span>
                                        @if ($upload->failed_rows > 0)
                                            · <span class="text-danger">{{ trans('product::seller.uploads.rows_failed', ['count' => $upload->failed_rows]) }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-ink-soft">
                                    {{ $upload->created_at->format('d M Y, H:i') }}
                                    @if ($upload->uploader)
                                        <span class="block text-xs text-muted">{{ $upload->uploader->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($upload->error_report_path)
                                            <x-theme::button variant="ghost" size="sm" :href="frontRoute('seller.upload.error_report', [$upload->id])">
                                                <x-theme::icon name="download" size="size-4" /> <span class="sr-only md:not-sr-only">{{ trans('product::seller.uploads.download_report') }}</span>
                                            </x-theme::button>
                                        @endif
                                        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.upload.show', [$upload->id])">{{ trans('product::seller.uploads.details') }}</x-theme::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $uploads->links() }}</div>
        @endif
    </div>
@endsection
