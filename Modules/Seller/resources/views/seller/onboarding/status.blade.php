@extends('seller::seller.onboarding.layout')

@section('title', trans('seller::onboarding.status.title'))

@section('content')
    @php
        $status = $seller->status->value;
        $icon = match ($status) {
            'pending_verification' => 'clock',
            'changes_requested' => 'pencil',
            default => 'alert',
        };
    @endphp
    <x-theme::card class="text-center">
        <span @class([
            'mx-auto flex size-14 items-center justify-center rounded-full',
            'bg-accent-soft text-accent' => $status === 'pending_verification',
            'bg-deal-soft text-deal' => $status === 'changes_requested',
            'bg-danger-soft text-danger' => ! in_array($status, ['pending_verification', 'changes_requested'], true),
        ])>
            <x-theme::icon :name="$icon" size="size-7" />
        </span>
        <h1 class="mt-4 text-xl font-semibold text-ink">{{ trans('seller::onboarding.status.'.$status.'.heading') }}</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ trans('seller::onboarding.status.'.$status.'.text') }}</p>

        @if ($seller->submitted_at && $status === 'pending_verification')
            <p class="mt-3 text-xs text-muted">{{ trans('seller::onboarding.status.submitted_on', ['date' => $seller->submitted_at->format('j M Y, g:i A')]) }}</p>
        @endif

        @if ($seller->status_reason && in_array($status, ['rejected', 'changes_requested'], true))
            <x-theme::alert type="warning" :title="trans('seller::onboarding.status.reason')" class="mt-6 text-left">{{ $seller->status_reason }}</x-theme::alert>
        @endif

        @if ($status === 'rejected')
            <form method="POST" action="{{ frontRoute('seller.onboarding.restart') }}" class="mt-6">
                @csrf
                <x-theme::button size="lg">{{ trans('seller::onboarding.status.rejected.restart') }}</x-theme::button>
            </form>
        @elseif ($status === 'changes_requested')
            <x-theme::button size="lg" class="mt-6" :href="frontRoute('seller.onboarding.show', [1])">{{ trans('seller::onboarding.status.changes_requested.continue') }}</x-theme::button>
        @endif
    </x-theme::card>

    @if ($status === 'pending_verification')
        <x-theme::card class="mt-6" padding="p-6">
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($steps as $number => $label)
                    <li class="flex items-center gap-2 text-sm text-ink-soft">
                        <x-theme::icon name="check-circle" size="size-4" class="text-success" /> {{ $label }}
                    </li>
                @endforeach
            </ul>
        </x-theme::card>
    @endif
@endsection
