@extends('customer::frontend.account.layout')

@section('title', trans('returns::front.wizard.title'))
@section('heading', trans('returns::front.wizard.title'))

@section('account')
    {{-- Step 2 (plan M15 §5.3): 1–5 photos (required for damaged / defective / wrong / not as described). --}}
    @include('returns::frontend.account.return.partials.wizard-header', ['current' => 2])

    <form method="POST" action="{{ frontRoute('customer.returns.store_step', ['step' => 'photos']) }}" enctype="multipart/form-data" class="space-y-5"
        x-data="{ loading: false }" x-on:submit="loading = true" data-step="photos">
        @csrf

        <x-theme::card padding="p-5" class="space-y-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ trans('returns::front.wizard.photos') }}</h2>
                <p class="mt-1 text-sm text-muted">
                    {{ trans($photosRequired ? 'returns::front.wizard.photos_required' : 'returns::front.wizard.photos_optional', ['max' => $maxImages]) }}
                </p>
            </div>

            @if ($draft->photos !== [])
                <ul class="grid grid-cols-3 gap-3 sm:grid-cols-5" data-draft-photos>
                    @foreach ($draft->photos as $photo)
                        @php
                            $name = basename($photo['path']);
                        @endphp
                        <li class="relative overflow-hidden rounded-lg border border-line bg-surface-alt">
                            <img src="{{ frontRoute('customer.returns.draft_photo', ['name' => $name]) }}" alt="" class="aspect-square w-full object-cover">
                            <label class="absolute inset-x-0 bottom-0 flex items-center gap-1.5 bg-surface/90 px-2 py-1 text-xs text-ink">
                                <input type="checkbox" name="remove[]" value="{{ $name }}" class="size-3.5 rounded accent-danger">
                                {{ trans('returns::front.wizard.remove_photo') }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-theme::dropzone name="photos" accept="image/jpeg,image/png,image/webp" :max-files="max(1, $maxImages - count($draft->photos))" :max-size-mb="(int) config('returns.uploads.max_size_kb', 5120) / 1024" />
            <p class="text-xs text-muted">{{ trans('returns::front.wizard.camera_hint') }}</p>
        </x-theme::card>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ frontRoute('customer.returns.create', ['orderNo' => $order->order_no, ['seller_order' => $sellerOrder->id]]) }}" class="text-sm font-medium text-accent hover:underline">{{ trans('returns::front.wizard.back') }}</a>
            <x-theme::button loading="loading">{{ trans('returns::front.wizard.continue') }}</x-theme::button>
        </div>
    </form>
@endsection
