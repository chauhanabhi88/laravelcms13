@extends('theme::layouts.seller.master')

@section('title', trans('returns::seller.title'))

@section('content')
    {{-- Supplier returns (FR-RET-006): to check (QC), incoming and closed returns of the seller's packages. --}}
    <div class="space-y-4">
        <p class="text-sm text-muted">{{ trans('returns::seller.hint') }}</p>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex gap-1 overflow-x-auto rounded-card border border-line bg-surface p-1" aria-label="{{ trans('returns::seller.title') }}">
                @foreach ($counts as $key => $count)
                    <a href="{{ frontRoute('seller.return.index', [['tab' => $key, 'search' => $search ?: null]]) }}"
                        @class(['flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-accent-soft text-accent' => $tab === $key, 'text-ink-soft hover:bg-surface-alt' => $tab !== $key])
                        @if ($tab === $key) aria-current="page" @endif data-tab="{{ $key }}">
                        {{ trans('returns::seller.tabs.'.$key) }}
                        <span class="tabular rounded-full bg-surface-alt px-1.5 text-xs text-muted">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
            <form method="GET" action="{{ frontRoute('seller.return.index') }}" class="flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <x-theme::input name="search" :value="$search" :placeholder="trans('returns::seller.search')" :label="null" class="w-56" />
                <x-theme::button variant="secondary" size="sm">{{ trans('returns::seller.search_button') }}</x-theme::button>
            </form>
        </div>

        <div id="collection">
            @include('returns::seller.return.partials.grid')
        </div>
    </div>
@endsection
