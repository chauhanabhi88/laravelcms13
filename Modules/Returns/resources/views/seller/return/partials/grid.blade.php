@if ($returns->isEmpty())
    <x-theme::empty-state icon="rotate-ccw" :title="trans('returns::seller.empty_title')" :text="trans('returns::seller.empty_text')" />
@else
    <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-alt text-left text-xs font-semibold tracking-wide text-muted uppercase">
                <tr>
                    <th scope="col" class="px-4 py-3">{{ trans('returns::seller.columns.return_no') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('returns::seller.columns.order') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('returns::seller.columns.items') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('returns::seller.columns.reason') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('returns::seller.columns.status') }}</th>
                    <th scope="col" class="px-4 py-3">{{ trans('returns::seller.columns.created_at') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($returns as $return)
                    <tr data-return="{{ $return->return_no }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ frontRoute('seller.return.show', ['returnNo' => $return->return_no]) }}" class="font-mono font-semibold text-accent hover:underline">{{ $return->return_no }}</a>
                            <span class="block text-xs text-muted">{{ trans('returns::returns.types.'.$return->type) }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono whitespace-nowrap">{{ $return->sellerOrder?->sub_order_no }}</td>
                        <td class="max-w-xs px-4 py-3">
                            @foreach ($return->items as $item)
                                <span class="block truncate">{{ $item->orderItem?->name }} <span class="text-muted">× {{ $item->quantity }}</span></span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3">{{ \Modules\Returns\Support\ReturnOptions::reason($return->reason_code) }}</td>
                        <td class="px-4 py-3"><x-theme::chip :variant="\Modules\Returns\Support\ReturnOptions::chip($return->status)">{{ \Modules\Returns\Support\ReturnOptions::status($return->status) }}</x-theme::chip></td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $return->created_at?->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $returns->links() }}</div>
@endif
