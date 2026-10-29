{{-- Coupon rule fields shared by the coupon form and the bulk form. $coupon: null on create / bulk. --}}
@php
    $value = fn (string $field, mixed $default = null) => old($field, $coupon?->$field ?? $default);
    $money = fn (?\Modules\Core\Support\Money $amount): ?string => $amount?->toDecimal();
    $scope = (string) old('scope', $coupon?->scope ?? 'all');
    $scopeIds = array_map('intval', (array) old('scope_ids', $coupon?->scopeIdList() ?? []));
    $channels = (array) old('channels', $coupon?->channels ?? []);
    $payments = (array) old('payment_methods', $coupon?->payment_methods ?? []);
    $dateValue = fn (string $field) => old($field, $coupon?->$field?->format('Y-m-d\TH:i'));
    $checked = fn (string $field, bool $default) => (bool) old($field, $coupon !== null ? (int) $coupon->$field === (int) config('core.yes') : $default);
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('promotion::coupon.titles.rules') }}</strong></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="name">{{ trans('promotion::coupon.fields.name') }}</label><span class="asterisk"> *</span>
                <input type="text" id="name" name="name" maxlength="100" required value="{{ $value('name') }}" class="form-control @error('name') is-invalid @enderror">
                @error('name')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="form-group col-md-6">
                <label for="description">{{ trans('promotion::coupon.fields.description') }}</label>
                <input type="text" id="description" name="description" maxlength="255" value="{{ $value('description') }}" class="form-control @error('description') is-invalid @enderror">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="type">{{ trans('promotion::coupon.fields.type') }}</label><span class="asterisk"> *</span>
                <select id="type" name="type" class="custom-select">
                    @foreach ($typeOptions as $option => $label)
                        <option value="{{ $option }}" @selected($value('type', 'flat') === $option)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="value">{{ trans('promotion::coupon.fields.value') }}</label><span class="asterisk"> *</span>
                <input type="number" step="0.01" min="0" id="value" name="value" required value="{{ old('value', $money($coupon?->value) ?? '0') }}" class="form-control @error('value') is-invalid @enderror">
                <small class="form-text text-muted">{{ trans('promotion::coupon.hints.value') }}</small>
                @error('value')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="form-group col-md-3">
                <label for="max_discount">{{ trans('promotion::coupon.fields.max_discount') }}</label>
                <input type="number" step="0.01" min="0" id="max_discount" name="max_discount" value="{{ old('max_discount', $money($coupon?->max_discount)) }}" class="form-control @error('max_discount') is-invalid @enderror">
            </div>
            <div class="form-group col-md-3">
                <label for="min_cart_value">{{ trans('promotion::coupon.fields.min_cart_value') }}</label>
                <input type="number" step="0.01" min="0" id="min_cart_value" name="min_cart_value" value="{{ old('min_cart_value', $money($coupon?->min_cart_value) ?? '0') }}" class="form-control @error('min_cart_value') is-invalid @enderror">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="scope">{{ trans('promotion::coupon.fields.scope') }}</label><span class="asterisk"> *</span>
                <select id="scope" name="scope" class="custom-select">
                    @foreach ($scopeOptions as $option => $label)
                        <option value="{{ $option }}" @selected($scope === $option)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-9">
                <label>{{ trans('promotion::coupon.fields.scope_ids') }}</label>
                <div data-scope-target="category">
                    <select name="scope_category_ids[]" multiple size="6" class="form-control">
                        @foreach ($categoryOptions as $id => $label)
                            <option value="{{ $id }}" @selected($scope === 'category' && in_array((int) $id, $scopeIds, true))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-scope-target="seller">
                    <select name="scope_seller_ids[]" multiple size="6" class="form-control">
                        @foreach ($sellerOptions as $id => $label)
                            <option value="{{ $id }}" @selected($scope === 'seller' && in_array((int) $id, $scopeIds, true))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-scope-target="brand">
                    <select name="scope_brand_ids[]" multiple size="6" class="form-control">
                        @foreach ($brandOptions as $id => $label)
                            <option value="{{ $id }}" @selected($scope === 'brand' && in_array((int) $id, $scopeIds, true))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-scope-target="product">
                    <input type="text" name="scope_product_ids" value="{{ $scope === 'product' ? implode(', ', $scopeIds) : '' }}" class="form-control" placeholder="12, 15, 108">
                    <small class="form-text text-muted">{{ trans('promotion::coupon.hints.product_ids') }}</small>
                </div>
                <small class="form-text text-muted">{{ trans('promotion::coupon.hints.scope_ids') }}</small>
                @error('scope_ids')<label class="error">{{ $message }}</label>@enderror
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="funded_by">{{ trans('promotion::coupon.fields.funded_by') }}</label><span class="asterisk"> *</span>
                <select id="funded_by" name="funded_by" class="custom-select">
                    @foreach ($fundingOptions as $option => $label)
                        <option value="{{ $option }}" @selected($value('funded_by', 'platform') === $option)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">{{ trans('promotion::coupon.hints.funded_by') }}</small>
            </div>
            <div class="form-group col-md-5">
                <label for="seller_id">{{ trans('promotion::coupon.fields.seller_id') }}</label>
                <select id="seller_id" name="seller_id" class="custom-select @error('seller_id') is-invalid @enderror">
                    <option value="">--</option>
                    @foreach ($sellerOptions as $id => $label)
                        <option value="{{ $id }}" @selected((string) $value('seller_id') === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('seller_id')<label class="error">{{ $message }}</label>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('promotion::coupon.titles.limits') }}</strong></div>
    <div class="card-body">
        <p class="text-muted">{{ trans('promotion::coupon.hints.limits') }}</p>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="usage_limit_total">{{ trans('promotion::coupon.fields.usage_limit_total') }}</label>
                <input type="number" min="1" id="usage_limit_total" name="usage_limit_total" value="{{ $value('usage_limit_total') }}" class="form-control @error('usage_limit_total') is-invalid @enderror">
            </div>
            <div class="form-group col-md-3">
                <label for="usage_limit_per_customer">{{ trans('promotion::coupon.fields.usage_limit_per_customer') }}</label>
                <input type="number" min="1" id="usage_limit_per_customer" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer', $coupon !== null ? $coupon->usage_limit_per_customer : 1) }}" class="form-control @error('usage_limit_per_customer') is-invalid @enderror">
            </div>
            <div class="form-group col-md-3">
                <label for="new_customer_days">{{ trans('promotion::coupon.fields.new_customer_days') }}</label>
                <input type="number" min="1" max="3650" id="new_customer_days" name="new_customer_days" value="{{ $value('new_customer_days') }}" class="form-control">
            </div>
            <div class="form-group col-md-3 d-flex align-items-end">
                <label class="mb-2"><input type="checkbox" name="first_order_only" value="1" @checked($checked('first_order_only', false))> {{ trans('promotion::coupon.fields.first_order_only') }}</label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="starts_at">{{ trans('promotion::coupon.fields.starts_at') }}</label>
                <input type="datetime-local" id="starts_at" name="starts_at" value="{{ $dateValue('starts_at') }}" class="form-control @error('starts_at') is-invalid @enderror">
            </div>
            <div class="form-group col-md-3">
                <label for="ends_at">{{ trans('promotion::coupon.fields.ends_at') }}</label>
                <input type="datetime-local" id="ends_at" name="ends_at" value="{{ $dateValue('ends_at') }}" class="form-control @error('ends_at') is-invalid @enderror">
                @error('ends_at')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="form-group col-md-3">
                <label>{{ trans('promotion::coupon.fields.channels') }}</label>
                @foreach ($channelOptions as $option => $label)
                    <div><label><input type="checkbox" name="channels[]" value="{{ $option }}" @checked(in_array($option, $channels, true))> {{ $label }}</label></div>
                @endforeach
                <small class="form-text text-muted">{{ trans('promotion::coupon.hints.channels') }}</small>
            </div>
            <div class="form-group col-md-3">
                <label>{{ trans('promotion::coupon.fields.payment_methods') }}</label>
                @foreach ($paymentOptions as $option => $label)
                    <div><label><input type="checkbox" name="payment_methods[]" value="{{ $option }}" @checked(in_array($option, $payments, true))> {{ $label }}</label></div>
                @endforeach
                <small class="form-text text-muted">{{ trans('promotion::coupon.hints.payment_methods') }}</small>
            </div>
        </div>
        <div class="form-row">
            @unless ($isBulk ?? false)
                <div class="form-group col-md-3"><label><input type="checkbox" name="is_auto" value="1" @checked($checked('is_auto', false))> {{ trans('promotion::coupon.fields.is_auto') }}</label></div>
            @endunless
            <div class="form-group col-md-3"><label><input type="checkbox" name="is_visible" value="1" @checked($checked('is_visible', ! ($isBulk ?? false)))> {{ trans('promotion::coupon.fields.is_visible') }}</label></div>
            <div class="form-group col-md-3"><label><input type="checkbox" name="stackable" value="1" @checked($checked('stackable', false))> {{ trans('promotion::coupon.fields.stackable') }}</label></div>
            <div class="form-group col-md-3"><label><input type="checkbox" name="status" value="1" @checked((bool) old('status', $coupon === null || $coupon->isEnabled()))> {{ trans('promotion::coupon.fields.status') }}</label></div>
        </div>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    jQuery(function() {
        function toggleScopeTargets() {
            var scope = jQuery('#scope').val();
            jQuery('[data-scope-target]').each(function() {
                jQuery(this).toggle(jQuery(this).data('scope-target') === scope);
            });
        }

        jQuery('#scope').on('change', toggleScopeTargets);
        toggleScopeTargets();
    });
</script>
@endpush
