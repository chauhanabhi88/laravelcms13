{{--
    Courier form shared by create and edit. Credentials only apply to the shiprocket driver;
    the stored password is never echoed back.
--}}
@php
    $courier = $courier ?? null;
    $driver = old('driver', $courier?->driver ?? 'shiprocket');
    $isEnabled = (int) old('status', $courier?->status ?? config('core.enabled')) === (int) config('core.enabled');
    $codChecked = (int) old('supports_cod', $courier?->supports_cod ?? config('core.yes')) === (int) config('core.yes');
    $reverseChecked = (int) old('supports_reverse', $courier?->supports_reverse ?? config('core.yes')) === (int) config('core.yes');
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('logistics::courier.titles.general') }}</strong></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                {{ normalText('code', 'logistics::courier.labels.code', $errors, old('code', $courier?->code), ['class' => 'form-control required', 'maxlength' => 40]) }}
            </div>
            <div class="col-md-5">
                {{ normalText('name', 'logistics::courier.labels.name', $errors, old('name', $courier?->name), ['class' => 'form-control required', 'maxlength' => 100]) }}
            </div>
            <div class="col-md-3">
                {{ normalText('priority', 'logistics::courier.labels.priority', $errors, old('priority', $courier?->priority ?? 0), ['class' => 'form-control', 'maxlength' => 4]) }}
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                {{ normalSelect('driver', 'logistics::courier.labels.driver', $errors, $drivers, $driver, ['id' => 'courier-driver']) }}
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <label>{{ trans('logistics::courier.labels.supports_cod') }}</label>
                <div><label class="switch"><input type="checkbox" value="{{ config('core.yes') }}" name="supports_cod" {{ $codChecked ? 'checked' : '' }}><span class="slider round"></span></label></div>
            </div>
            <div class="col-md-4">
                <label>{{ trans('logistics::courier.labels.supports_reverse') }}</label>
                <div><label class="switch"><input type="checkbox" value="{{ config('core.yes') }}" name="supports_reverse" {{ $reverseChecked ? 'checked' : '' }}><span class="slider round"></span></label></div>
            </div>
            <div class="col-md-4">
                <label>{{ trans('logistics::courier.labels.status') }}</label>
                <div><label class="switch"><input type="checkbox" value="{{ config('core.enabled') }}" name="status" {{ $isEnabled ? 'checked' : '' }}><span class="slider round"></span></label></div>
            </div>
        </div>
    </div>
</div>

<div class="card card-info card-outline mt-3" id="courier-credentials">
    <div class="card-header"><strong>{{ trans('logistics::courier.titles.credentials') }}</strong></div>
    <div class="card-body">
        <p class="text-muted">{{ trans('logistics::courier.labels.password_help') }}</p>
        <div class="row">
            <div class="col-md-6">
                {{ normalText('credentials_email', 'logistics::courier.labels.email', $errors, old('credentials_email', $courier?->credential('email')), ['class' => 'form-control', 'maxlength' => 150, 'autocomplete' => 'off']) }}
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="credentials_password">{{ trans('logistics::courier.labels.password') }}</label>
                    <input type="password" id="credentials_password" name="credentials_password" class="form-control" maxlength="150" autocomplete="new-password" value="">
                    @error('credentials_password')<label class="error">{{ $message }}</label>@enderror
                </div>
            </div>
        </div>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    (function () {
        var toggle = function () {
            jQuery('#courier-credentials').toggle(jQuery('#courier-driver').val() === 'shiprocket');
        };
        jQuery(document).on('change', '#courier-driver', toggle);
        jQuery(toggle);
    })();
</script>
@endpush
