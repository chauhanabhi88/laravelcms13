@extends('theme::layouts.seller.master')

@section('title', trans('product::seller.uploads.show_title', ['id' => $upload->id]))

@section('page_actions')
    <div class="flex flex-wrap gap-2">
        <x-theme::button variant="secondary" size="sm" :href="frontRoute('seller.upload.file', [$upload->id])">
            <x-theme::icon name="download" size="size-4" /> {{ trans('product::seller.uploads.download_original') }}
        </x-theme::button>
        @if ($upload->error_report_path)
            <x-theme::button size="sm" :href="frontRoute('seller.upload.error_report', [$upload->id])">
                <x-theme::icon name="download" size="size-4" /> {{ trans('product::seller.uploads.download_report') }}
            </x-theme::button>
        @endif
    </div>
@endsection

@section('content')
    <div class="space-y-5" x-data="uploadStatus({ url: @js(frontRoute('seller.upload.status', [$upload->id])), finished: @js($upload->isFinished()) })">
        @unless ($upload->isFinished())
            <x-theme::alert type="info">
                <span class="inline-flex items-center gap-2"><x-theme::icon name="spinner" size="size-4" class="animate-spin" /> {{ trans('product::seller.uploads.processing_note') }}</span>
            </x-theme::alert>
        @endunless

        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-muted">{{ trans('product::seller.uploads.summary.status') }}</dt>
                    <dd class="mt-1"><x-theme::chip :variant="$upload->statusChip()">{{ $upload->statusLabel() }}</x-theme::chip></dd>
                </div>
                <div>
                    <dt class="text-muted">{{ trans('product::seller.uploads.summary.type') }}</dt>
                    <dd class="mt-1 text-ink">{{ trans('product::seller.uploads.types.'.$upload->type) }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ trans('product::seller.uploads.summary.file') }}</dt>
                    <dd class="mt-1 truncate text-ink">{{ $upload->original_name }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ trans('product::seller.uploads.summary.uploaded_at') }}</dt>
                    <dd class="mt-1 text-ink">{{ $upload->created_at->format('d M Y, H:i') }}@if ($upload->uploader) · {{ $upload->uploader->name }}@endif</dd>
                </div>
                @if ($categoryLabel)
                    <div class="sm:col-span-2">
                        <dt class="text-muted">{{ trans('product::seller.uploads.summary.category') }}</dt>
                        <dd class="mt-1 text-ink">{{ $categoryLabel }}@if ($upload->savesAsDraft()) · <span class="text-muted">{{ trans('product::seller.uploads.summary.draft_mode') }}</span>@endif</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-muted">{{ trans('product::seller.uploads.summary.total') }}</dt>
                    <dd class="mt-1 text-lg font-semibold text-ink tabular-nums">{{ $upload->total_rows }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ trans('product::seller.uploads.summary.success') }} / {{ trans('product::seller.uploads.summary.failed') }}</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums"><span class="text-success">{{ $upload->success_rows }}</span> / <span @class(['text-danger' => $upload->failed_rows > 0, 'text-ink' => $upload->failed_rows === 0])>{{ $upload->failed_rows }}</span></dd>
                </div>
            </dl>
            @if ($upload->error_report_path)
                <p class="mt-4 text-xs text-muted">{{ trans('product::seller.uploads.report_hint') }}</p>
            @endif
        </section>

        @if ($upload->isFinished())
            <section class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <h2 class="p-5 pb-0 text-base font-semibold text-ink">{{ trans('product::seller.uploads.problems') }}</h2>
                @if ($errorsByRow->isEmpty())
                    <p class="p-5 text-sm text-muted">{{ trans('product::seller.uploads.no_problems') }}</p>
                @else
                    <table class="mt-3 min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ trans('product::seller.uploads.row') }}</th>
                                <th scope="col" class="px-4 py-2">{{ trans('product::seller.uploads.column') }}</th>
                                <th scope="col" class="px-4 py-2">{{ trans('product::seller.uploads.message') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($errorsByRow as $row => $problems)
                                @foreach ($problems as $problem)
                                    <tr>
                                        <td class="px-4 py-2 align-top whitespace-nowrap text-muted tabular-nums">{{ $row === 0 ? trans('product::seller.uploads.whole_file') : $row }}</td>
                                        <td class="px-4 py-2 align-top text-ink-soft">{{ $problem->column ? ($columnLabels[$problem->column] ?? $problem->column) : '—' }}</td>
                                        <td class="px-4 py-2 align-top">
                                            <x-theme::chip :variant="$problem->isWarning() ? 'deal' : 'danger'" class="mr-1">{{ $problem->isWarning() ? trans('product::seller.uploads.warning') : trans('product::seller.uploads.error') }}</x-theme::chip>
                                            <span class="text-ink">{{ $problem->message }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endif
    </div>
@endsection
