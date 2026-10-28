@extends('theme::layouts.frontend.master')

@section('title', trans('payment::front.pay.title'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@php
    $orderNo = $order->payableOrderNo();
    $amount = $payment->amount->format();
@endphp

@section('content')
    {{--
        Razorpay Standard Checkout (plan M11 §5.1). The button opens Checkout.js; its success
        handler posts the signed result to payment.callback, and a failure posts the error so the
        attempt is logged. The server verifies the signature and asks Razorpay for the status.
    --}}
    <div class="container-page max-w-xl py-8 lg:py-12">
        <h1 class="text-xl font-semibold text-ink lg:text-2xl">{{ trans('payment::front.pay.heading', ['order_no' => $orderNo]) }}</h1>

        <div class="mt-4 space-y-3">
            {{-- After a failed attempt the bank's reason below replaces the generic message. --}}
            @if (session('error') && ! ($lastAttempt && session('error') === trans('payment::front.errors.payment_failed')))
                <x-theme::alert type="error">{{ session('error') }}</x-theme::alert>
            @endif
            @if (session('success'))
                <x-theme::alert type="info">{{ session('success') }}</x-theme::alert>
            @endif
            @if ($gatewayError)
                <x-theme::alert type="error">{{ $gatewayError }}</x-theme::alert>
            @endif
            @if ($lastAttempt)
                <x-theme::alert type="warning">{{ trans('payment::front.pay.last_failed', ['reason' => $lastAttempt->error_description ?: trans('payment::front.errors.payment_failed')]) }}</x-theme::alert>
            @endif
        </div>

        <x-theme::card class="mt-5" padding="p-5 sm:p-6">
            <div class="flex items-baseline justify-between gap-4">
                <span class="text-sm text-ink-soft">{{ trans('payment::front.pay.amount') }}</span>
                <span class="tabular text-2xl font-bold text-ink" id="payment-amount">{{ $amount }}</span>
            </div>

            <p class="mt-3 text-sm text-muted">{{ trans('payment::front.pay.methods') }}</p>

            <p class="mt-4 text-sm text-ink-soft" id="payment-countdown" data-seconds="{{ $secondsLeft }}">
                {{ trans('payment::front.pay.time_left') }}
                <span class="tabular font-semibold text-ink" data-countdown>{{ sprintf('%02d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60) }}</span>
            </p>

            @if ($options !== [] && ! $isFake)
                <x-theme::button type="button" size="lg" class="mt-5 w-full" id="razorpay-pay-button" disabled>
                    {{ trans('payment::front.pay.button', ['amount' => $amount]) }}
                </x-theme::button>
                <noscript><p class="mt-3 text-sm text-danger">{{ trans('payment::front.pay.no_js') }}</p></noscript>
            @elseif ($options === [])
                <form method="POST" action="{{ frontRoute('payment.retry', ['orderNo' => $orderNo]) }}" class="mt-5">
                    @csrf
                    <x-theme::button size="lg" class="w-full">{{ trans('payment::front.pay.retry') }}</x-theme::button>
                </form>
            @endif

            @if ($isFake && $options !== [])
                <div class="mt-5 space-y-3 rounded-xl border border-dashed border-line-strong p-4" id="fake-gateway-panel">
                    <p class="text-sm font-medium text-warning">{{ trans('payment::front.pay.test_mode') }}</p>
                    <form method="POST" action="{{ frontRoute('payment.simulate', ['orderNo' => $orderNo]) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="captured">
                        <x-theme::button size="lg" class="w-full">{{ trans('payment::front.pay.simulate_success') }}</x-theme::button>
                    </form>
                    <form method="POST" action="{{ frontRoute('payment.simulate', ['orderNo' => $orderNo]) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="failed">
                        <x-theme::button variant="secondary" class="w-full">{{ trans('payment::front.pay.simulate_failure') }}</x-theme::button>
                    </form>
                </div>
            @endif

            <p class="mt-4 flex items-center justify-center gap-2 text-xs text-muted">
                <x-theme::icon name="lock" size="size-3.5" />
                {{ trans('payment::front.pay.secure') }}
            </p>
        </x-theme::card>

        <x-theme::card class="mt-4" padding="p-5 sm:p-6" id="cod-switch">
            <h2 class="text-base font-semibold text-ink">{{ trans('payment::front.pay.cod_heading') }}</h2>
            @if ($codEligibility->allowed)
                <form method="POST" action="{{ frontRoute('payment.switch_cod', ['orderNo' => $orderNo]) }}" class="mt-3">
                    @csrf
                    <x-theme::button variant="secondary" class="w-full">{{ trans('payment::front.pay.cod_button') }}</x-theme::button>
                </form>
            @else
                <p class="mt-2 text-sm text-muted">{{ trans('payment::front.pay.cod_unavailable', ['reason' => $codEligibility->reason]) }}</p>
            @endif
        </x-theme::card>

        {{-- Posted by the Checkout.js handlers below. --}}
        <form method="POST" action="{{ frontRoute('payment.callback', ['orderNo' => $orderNo]) }}" id="razorpay-callback-form" class="hidden">
            @csrf
            <input type="hidden" name="razorpay_payment_id">
            <input type="hidden" name="razorpay_order_id">
            <input type="hidden" name="razorpay_signature">
            <input type="hidden" name="error[code]" disabled>
            <input type="hidden" name="error[description]" disabled>
            <input type="hidden" name="error[reason]" disabled>
            <input type="hidden" name="error[metadata][payment_id]" disabled>
        </form>
    </div>
@endsection

@push('js-stack')
    <script>
        (function () {
            var countdown = document.getElementById('payment-countdown');
            if (countdown) {
                var seconds = parseInt(countdown.dataset.seconds, 10) || 0;
                var label = countdown.querySelector('[data-countdown]');
                var timer = setInterval(function () {
                    seconds = Math.max(0, seconds - 1);
                    label.textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
                    if (seconds === 0) {
                        clearInterval(timer);
                        window.location.reload();
                    }
                }, 1000);
            }
        })();
    </script>

    @if ($options !== [] && ! $isFake)
        <script src="{{ $checkoutJs }}" defer></script>
        <script>
            (function () {
                var options = @json($options);
                var button = document.getElementById('razorpay-pay-button');
                var form = document.getElementById('razorpay-callback-form');
                var submitted = false;

                function post(fields) {
                    if (submitted) {
                        return;
                    }
                    submitted = true;
                    Object.keys(fields).forEach(function (name) {
                        var input = form.querySelector('[name="' + name + '"]');
                        if (input) {
                            input.disabled = false;
                            input.value = fields[name] || '';
                        }
                    });
                    form.submit();
                }

                options.handler = function (response) {
                    post({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature
                    });
                };
                options.modal = { confirm_close: true };

                function open() {
                    var checkout = new window.Razorpay(options);
                    checkout.on('payment.failed', function (response) {
                        var error = response.error || {};
                        post({
                            'error[code]': error.code,
                            'error[description]': error.description,
                            'error[reason]': error.reason,
                            'error[metadata][payment_id]': (error.metadata || {}).payment_id
                        });
                    });
                    checkout.open();
                }

                window.addEventListener('load', function () {
                    if (! window.Razorpay) {
                        return;
                    }
                    button.disabled = false;
                    button.addEventListener('click', open);
                    @if (! session('error') && ! session('success') && ! $lastAttempt)
                        open();
                    @endif
                });
            })();
        </script>
    @endif
@endpush
