@extends('customer::frontend.account.layout')

@section('title', trans('customer::customer_front.deletion.title'))
@section('heading', trans('customer::customer_front.deletion.title'))
@section('subheading', trans('customer::customer_front.deletion.subtitle'))

@section('account')
    <x-theme::card class="max-w-2xl">
        @if ($pendingRequest)
            <x-theme::alert type="warning">
                {{ trans('customer::customer_front.deletion.pending', ['date' => $pendingRequest->scheduled_for?->translatedFormat('j M Y')]) }}
            </x-theme::alert>
            <form method="POST" action="{{ frontRoute('customer.deletion.cancel') }}" class="mt-6 flex justify-end">
                @csrf
                <x-theme::button>{{ trans('customer::customer_front.deletion.cancel') }}</x-theme::button>
            </form>
        @else
            <h2 class="text-base font-semibold text-ink">{{ trans('customer::customer_front.deletion.what_happens') }}</h2>
            <ul class="mt-4 space-y-3 text-sm text-ink-soft">
                @foreach (['grace', 'erase', 'orders', 'open'] as $point)
                    <li class="flex gap-2.5">
                        <x-theme::icon name="info" size="size-4" class="mt-0.5 shrink-0 text-muted" />
                        <span>{{ trans('customer::customer_front.deletion.points.'.$point, ['days' => $graceDays]) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 border-t border-line pt-6">
                @if ($destination === null)
                    <x-theme::alert type="warning">{{ trans('customer::customer_front.deletion.no_contact') }}</x-theme::alert>
                @elseif ($codeSent || $errors->has('code'))
                    <form method="POST" action="{{ frontRoute('customer.deletion.store') }}" class="space-y-5" x-data="{ loading: false }" x-on:submit="loading = true">
                        @csrf
                        <p class="text-sm text-muted">{{ trans('customer::customer_front.otp.verify_subtitle', ['to' => $destination]) }}</p>
                        @include('customer::frontend.auth.partials.otp-boxes', ['length' => $codeLength, 'idPrefix' => 'delete-otp'])
                        <x-theme::field-error name="code" class="justify-center" />
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <x-theme::button variant="ghost" :href="frontRoute('customer.myaccount')">{{ trans('customer::customer_front.buttons.cancel') }}</x-theme::button>
                            <x-theme::button variant="danger" loading="loading">{{ trans('customer::customer_front.deletion.confirm') }}</x-theme::button>
                        </div>
                    </form>
                @else
                    <form method="POST" action="{{ frontRoute('customer.deletion.code') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between" x-data="{ loading: false }" x-on:submit="loading = true">
                        @csrf
                        <p class="text-sm text-muted">{{ trans('customer::customer_front.deletion.confirm_with_code', ['to' => $destination]) }}</p>
                        <x-theme::button variant="danger" loading="loading">{{ trans('customer::customer_front.deletion.send_code') }}</x-theme::button>
                    </form>
                @endif
            </div>
        @endif
    </x-theme::card>
@endsection
