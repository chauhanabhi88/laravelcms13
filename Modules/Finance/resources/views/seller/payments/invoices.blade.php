@extends('theme::layouts.seller.master')

@section('title', trans('finance::seller.invoices.title'))

@section('content')
    {{-- Tax invoices, credit notes and monthly fee invoices (FR-FIN-006). --}}
    <div class="space-y-4">
        @include('finance::seller.payments.partials.tabs', ['current' => 'invoices'])

        <nav class="flex gap-2" aria-label="{{ trans('finance::seller.invoices.title') }}">
            @foreach (\Modules\Finance\Models\TaxDocument::TYPES as $docType)
                <a href="{{ frontRoute('seller.payments.invoices', [['type' => $docType]]) }}"
                    @class(['rounded-full border px-3 py-1 text-sm', 'border-accent bg-accent-soft text-accent' => $type === $docType, 'border-line text-ink-soft hover:bg-surface-alt' => $type !== $docType])>{{ trans('finance::finance.document_types.'.$docType) }}</a>
            @endforeach
        </nav>

        @if ($documents->isEmpty())
            <x-theme::empty-state icon="document" :title="trans('finance::seller.invoices.empty_title')" :text="trans('finance::seller.invoices.empty_text.'.$type)" />
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.invoices.columns.number') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.invoices.columns.date') }}</th>
                            <th scope="col" class="px-4 py-3">{{ trans('finance::seller.invoices.columns.reference') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.invoices.columns.taxable') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.invoices.columns.gst') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ trans('finance::seller.invoices.columns.total') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ trans('finance::seller.invoices.download') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($documents as $document)
                            <tr data-document="{{ $document->id }}">
                                <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $document->number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $document->document_date?->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $document->period ?? $document->sellerOrder?->sub_order_no ?? '-' }}@if ($document->reference_number)<span class="block text-xs text-muted">{{ $document->reference_number }}</span>@endif</td>
                                <td class="tabular px-4 py-3 text-right">{{ $document->taxable_value->format(true) }}</td>
                                <td class="tabular px-4 py-3 text-right">{{ $document->taxTotal()->format(true) }}</td>
                                <td class="tabular px-4 py-3 text-right font-semibold">{{ $document->total->format(true) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ frontRoute('seller.payments.invoices.download', [$document->id]) }}" class="inline-flex items-center gap-1 text-accent hover:underline">
                                        <x-theme::icon name="download" size="size-4" /> {{ trans('finance::seller.invoices.download') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $documents->links() }}</div>
        @endif
    </div>
@endsection
