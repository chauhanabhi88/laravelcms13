{{-- Brand form shared by create and edit. $catalogBrand is null on create. --}}
@php
    $brand = $catalogBrand ?? null;
    $isEnabled = (int) old('status', $brand?->status ?? config('core.enabled')) === (int) config('core.enabled');
    $isVerified = (int) old('is_verified', $brand?->is_verified ?? config('core.no')) === (int) config('core.yes');
    $defaultLocale = app()->getLocale();
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('catalog::catalog_brand.titles.general') }}</strong></div>
    <div class="card-body">
        @if ($brand?->isPendingRequest())
            <div class="alert alert-warning d-flex align-items-center justify-content-between">
                <span>{{ trans('catalog::catalog_brand.labels.requested_by', ['id' => $brand->requested_by_seller_id]) }}</span>
                @can('admin.catalog_brand.approve')
                    <button type="submit" form="approve_form" class="btn btn-success btn-sm">{{ trans('catalog::catalog_brand.labels.approve_button') }}</button>
                @endcan
            </div>
        @endif

        @foreach ($languageOptions as $locale => $language)
            <div class="form-group">
                <label for="name-{{ $locale }}">{{ trans('catalog::catalog_brand.labels.name') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label><span class="asterisk"> *</span>
                <input type="text" id="name-{{ $locale }}" name="{{ $locale }}[name]" maxlength="120"
                    class="form-control required {{ $errors->has($locale.'.name') ? 'is-invalid' : '' }}"
                    value="{{ old($locale.'.name', $brand?->translate($locale)?->name) }}"
                    @if ($locale === $defaultLocale) data-slug="source" @endif>
                @error($locale.'.name')<label class="error">{{ $message }}</label>@enderror
            </div>
        @endforeach

        {{ normalText('slug', 'catalog::catalog_brand.labels.slug', $errors, old('slug', $brand?->slug), ['class' => 'form-control required', 'data-slug' => 'target', 'maxlength' => 160]) }}

        @foreach ($languageOptions as $locale => $language)
            <div class="form-group">
                <label for="description-{{ $locale }}">{{ trans('catalog::catalog_brand.labels.description') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label>
                <textarea id="description-{{ $locale }}" name="{{ $locale }}[description]" rows="3" class="form-control">{{ old($locale.'.description', $brand?->translate($locale)?->description) }}</textarea>
                @error($locale.'.description')<label class="error">{{ $message }}</label>@enderror
            </div>
        @endforeach

        {{ normalSelect('size_chart_id', 'catalog::catalog_brand.labels.size_chart_id', $errors, $sizeChartOptions, old('size_chart_id', $brand?->size_chart_id)) }}

        <div class="form-group">
            <label for="logo">{{ trans('catalog::catalog_brand.labels.logo') }}</label>
            @if ($brand?->logo)
                <div class="mb-2">
                    <img src="{{ getImageUrl(['module' => config('catalog.upload.brand'), 'image' => $brand->logo, 'defualt-image' => true]) }}" alt="" style="max-height: 80px" class="img-thumbnail">
                    <label class="ml-2"><input type="checkbox" name="remove_logo" value="1"> {{ trans('catalog::catalog_category.labels.remove_image') }}</label>
                </div>
            @endif
            <input type="file" id="logo" name="logo" class="form-control-file" accept="image/png,image/jpeg,image/webp">
            @error('logo')<label class="error">{{ $message }}</label>@enderror
        </div>

        <div class="row">
            <div class="col-md-6">
                <label>{{ trans('catalog::catalog_brand.labels.is_verified') }}</label>
                <div>
                    <label class="switch">
                        <input type="checkbox" value="{{ config('core.yes') }}" name="is_verified" {{ $isVerified ? 'checked' : '' }}>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <label>{{ trans('catalog::catalog_brand.labels.status') }}</label>
                <div>
                    <label class="switch">
                        <input type="checkbox" value="{{ config('core.enabled') }}" name="status" {{ $isEnabled ? 'checked' : '' }}>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    jQuery(function() {
        jQuery('#main_form').validate({
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });

        @if (! $brand)
        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });
        @endif
    });
</script>
@endpush
