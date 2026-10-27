{{--
    Six one-digit boxes named code[] (VerifyOtpRequest joins them). With JavaScript the
    otpInput component adds auto-advance, paste and an "otp-complete" event.
    $length: number of digits. $idPrefix: unique id prefix.
--}}
@php
    $length ??= (int) config('core.otp.length', 6);
    $idPrefix ??= 'otp';
    $autofocus ??= true;
@endphp
<fieldset x-data="otpInput({ length: {{ $length }} })">
    <legend class="sr-only">{{ trans('customer::customer_front.otp.code_label') }}</legend>
    <div class="flex justify-center gap-2 sm:gap-3">
        @for ($i = 0; $i < $length; $i++)
            <input type="text" name="code[]" id="{{ $idPrefix }}-{{ $i }}" data-otp-box inputmode="numeric" pattern="[0-9]*" maxlength="{{ $i === 0 ? $length : 1 }}"
                @if ($i === 0) autocomplete="one-time-code" @if ($autofocus) autofocus @endif @else autocomplete="off" @endif
                aria-label="{{ trans('customer::customer_front.otp.digit_label', ['position' => $i + 1, 'length' => $length]) }}"
                x-on:input="onInput({{ $i }}, $event)" x-on:keydown="onKeydown({{ $i }}, $event)" x-on:paste="onPaste({{ $i }}, $event)" x-on:focus="$event.target.select()"
                @class([
                    'tabular size-11 rounded-lg border bg-surface text-center text-lg font-semibold text-ink shadow-soft transition sm:size-12',
                    'focus:border-accent focus:ring-4 focus:ring-accent/15 focus:outline-none',
                    'border-danger' => $errors->has('code'),
                    'border-line-strong' => ! $errors->has('code'),
                ])>
        @endfor
    </div>
</fieldset>
