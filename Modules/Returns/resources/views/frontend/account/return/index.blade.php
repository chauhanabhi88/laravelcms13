@extends('customer::frontend.account.layout')

@section('title', trans('returns::front.list.title'))
@section('heading', trans('returns::front.list.title'))

@section('account')
    {{-- The customer's returns and exchanges, newest first. --}}
    @if ($returns->isEmpty())
        <x-theme::empty-state icon="rotate-ccw" :title="trans('returns::front.list.empty')" :text="trans('returns::front.list.empty_text')" />
    @else
        <ul class="space-y-3">
            @foreach ($returns as $return)
                <li>
                    <a href="{{ frontRoute('customer.returns.show', ['returnNo' => $return->return_no]) }}" class="block" data-return="{{ $return->return_no }}">
                        <x-theme::card padding="p-4 sm:p-5" class="transition hover:border-accent">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="tabular text-sm font-semibold text-ink">{{ $return->return_no }}</p>
                                    <p class="text-xs text-muted">{{ trans('returns::front.list.meta', ['type' => trans('returns::returns.types.'.$return->type), 'order_no' => $return->sellerOrder?->sub_order_no, 'date' => $return->created_at?->translatedFormat('j M Y')]) }}</p>
                                    <p class="mt-1 truncate text-sm text-ink-soft">{{ $return->items->map(fn ($item) => $item->orderItem?->name)->filter()->implode(', ') }}</p>
                                </div>
                                <div class="text-right">
                                    <x-theme::chip :variant="\Modules\Returns\Support\ReturnOptions::chip($return->status)">{{ \Modules\Returns\Support\ReturnOptions::status($return->status) }}</x-theme::chip>
                                    @if (! $return->isExchange() && $return->refund_amount->isPositive())
                                        <p class="tabular mt-1 text-sm font-semibold text-ink">{{ $return->refund_amount->format() }}</p>
                                    @endif
                                </div>
                            </div>
                        </x-theme::card>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $returns->links() }}</div>
    @endif
@endsection
