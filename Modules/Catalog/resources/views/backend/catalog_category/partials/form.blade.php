{{--
    Category form shared by create and edit. $catalogCategory is null on create.
    Commerce defaults left blank are inherited; the placeholder shows the inherited value.
--}}
@php
    $category = $catalogCategory ?? null;
    $value = fn (string $field, $default = null) => old($field, $category ? $category->getAttribute($field) : $default);
    $inheritedLabel = fn ($inheritedValue) => trans('catalog::catalog_category.labels.inherited', ['value' => $inheritedValue === null || $inheritedValue === '' ? trans('catalog::catalog_category.labels.not_set') : $inheritedValue]);
    $isEnabled = (int) old('status', $category?->status ?? config('core.enabled')) === (int) config('core.enabled');
    $inMenu = (int) old('show_in_menu', $category?->show_in_menu ?? config('core.yes')) === (int) config('core.yes');
    $defaultLocale = app()->getLocale();
@endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a href="#category-general" class="nav-link active" data-toggle="tab" role="tab">{{ trans('catalog::catalog_category.titles.general') }}</a></li>
    <li class="nav-item"><a href="#category-commerce" class="nav-link" data-toggle="tab" role="tab">{{ trans('catalog::catalog_category.titles.commerce') }}</a></li>
    <li class="nav-item"><a href="#category-media" class="nav-link" data-toggle="tab" role="tab">{{ trans('catalog::catalog_category.titles.media') }}</a></li>
    <li class="nav-item"><a href="#category-seo" class="nav-link" data-toggle="tab" role="tab">{{ trans('catalog::catalog_category.titles.seo') }}</a></li>
</ul>

<div class="card card-info card-outline card-outline-tabs">
    <div class="card-body">
        <div class="tab-content">
            {{-- General --}}
            <div class="tab-pane active" id="category-general" role="tabpanel">
                @foreach ($languageOptions as $locale => $language)
                    <div class="form-group">
                        <label for="name-{{ $locale }}">{{ trans('catalog::catalog_category.labels.name') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label><span class="asterisk"> *</span>
                        <input type="text" id="name-{{ $locale }}" name="{{ $locale }}[name]" maxlength="120"
                            class="form-control required {{ $errors->has($locale.'.name') ? 'is-invalid' : '' }}"
                            value="{{ old($locale.'.name', $category?->translate($locale)?->name) }}"
                            @if ($locale === $defaultLocale) data-slug="source" @endif>
                        @error($locale.'.name')<label class="error">{{ $message }}</label>@enderror
                    </div>
                @endforeach

                {{ normalText('slug', 'catalog::catalog_category.labels.slug', $errors, $value('slug'), ['class' => 'form-control required', 'data-slug' => 'target', 'maxlength' => 160]) }}
                {{ normalSelect('parent_id', 'catalog::catalog_category.labels.parent', $errors, $parentOptions, $value('parent_id', ($parent ?? null)?->id)) }}

                <div class="row">
                    <div class="col-md-6">
                        {{ normalInputOfType('number', 'sort_order', 'catalog::catalog_category.labels.sort_order', $errors, $value('sort_order'), ['min' => 0]) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalText('icon', 'catalog::catalog_category.labels.icon', $errors, $value('icon'), ['placeholder' => 'catalog::catalog_category.labels.icon_help']) }}
                    </div>
                </div>

                @foreach ($languageOptions as $locale => $language)
                    <div class="form-group">
                        <label for="description-{{ $locale }}">{{ trans('catalog::catalog_category.labels.description') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label>
                        <textarea id="description-{{ $locale }}" name="{{ $locale }}[description]" rows="3" class="form-control">{{ old($locale.'.description', $category?->translate($locale)?->description) }}</textarea>
                        @error($locale.'.description')<label class="error">{{ $message }}</label>@enderror
                    </div>
                @endforeach

                <div class="row">
                    <div class="col-md-6">
                        <label>{{ trans('catalog::catalog_category.labels.show_in_menu') }}</label>
                        <div>
                            <label class="switch">
                                <input type="checkbox" value="{{ config('core.yes') }}" name="show_in_menu" {{ $inMenu ? 'checked' : '' }}>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label>{{ trans('catalog::catalog_category.labels.status') }}</label>
                        <div>
                            <label class="switch">
                                <input type="checkbox" value="{{ config('core.enabled') }}" name="status" {{ $isEnabled ? 'checked' : '' }}>
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Commerce defaults --}}
            <div class="tab-pane" id="category-commerce" role="tabpanel">
                <p class="text-muted">{{ trans('catalog::catalog_category.labels.commerce_help') }}</p>
                <div class="row">
                    <div class="col-md-6">
                        {{ normalInputOfType('number', 'gst_percent', 'catalog::catalog_category.labels.gst_percent', $errors, $value('gst_percent'), ['min' => 0, 'max' => 40, 'step' => '0.01', 'placeholder' => $inheritedLabel($inherited['gst_percent'] !== null ? rtrim(rtrim($inherited['gst_percent'], '0'), '.').'%' : null)]) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalText('hsn_code', 'catalog::catalog_category.labels.hsn_code', $errors, $value('hsn_code'), ['maxlength' => 8, 'placeholder' => $inheritedLabel($inherited['hsn_code'])]) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalInputOfType('number', 'return_window_days', 'catalog::catalog_category.labels.return_window_days', $errors, $value('return_window_days'), ['min' => 0, 'max' => 90, 'placeholder' => $inheritedLabel($inherited['return_window_days'])]) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalSelect('return_type', 'catalog::catalog_category.labels.return_type', $errors, $returnTypeOptions, $value('return_type')) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalInputOfType('number', 'dispatch_sla_hours', 'catalog::catalog_category.labels.dispatch_sla_hours', $errors, $value('dispatch_sla_hours'), ['min' => 1, 'max' => 720, 'placeholder' => $inheritedLabel($inherited['dispatch_sla_hours'])]) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalSelect('size_chart_id', 'catalog::catalog_category.labels.size_chart_id', $errors, $sizeChartOptions, $value('size_chart_id')) }}
                    </div>
                    <div class="col-md-6">
                        {{ normalInputOfType('number', 'min_selling_price', 'catalog::catalog_category.labels.min_selling_price', $errors, $value('min_selling_price'), ['min' => 0, 'step' => '0.01', 'maxlength' => 15, 'placeholder' => $inheritedLabel($inherited['min_selling_price'])]) }}
                    </div>
                </div>
            </div>

            {{-- Images --}}
            <div class="tab-pane" id="category-media" role="tabpanel">
                @foreach (['image', 'banner_image'] as $imageField)
                    <div class="form-group">
                        <label for="{{ $imageField }}">{{ trans('catalog::catalog_category.labels.'.$imageField) }}</label>
                        @if ($category?->$imageField)
                            <div class="mb-2">
                                <img src="{{ getImageUrl(['module' => config('catalog.upload.category'), 'image' => $category->$imageField, 'defualt-image' => true]) }}" alt="" style="max-height: 120px" class="img-thumbnail">
                                <label class="ml-2"><input type="checkbox" name="remove_{{ $imageField }}" value="1"> {{ trans('catalog::catalog_category.labels.remove_image') }}</label>
                            </div>
                        @endif
                        <input type="file" id="{{ $imageField }}" name="{{ $imageField }}" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                        @error($imageField)<label class="error">{{ $message }}</label>@enderror
                    </div>
                @endforeach
            </div>

            {{-- SEO --}}
            <div class="tab-pane" id="category-seo" role="tabpanel">
                @foreach ($languageOptions as $locale => $language)
                    @if (count($languageOptions) > 1)
                        <h6 class="mt-2">{{ $language }}</h6>
                    @endif
                    @foreach (['meta_title' => 255, 'meta_keywords' => 255] as $field => $max)
                        <div class="form-group">
                            <label for="{{ $field }}-{{ $locale }}">{{ trans('catalog::catalog_category.labels.'.$field) }}</label>
                            <input type="text" id="{{ $field }}-{{ $locale }}" name="{{ $locale }}[{{ $field }}]" maxlength="{{ $max }}" class="form-control" value="{{ old($locale.'.'.$field, $category?->translate($locale)?->$field) }}">
                            @error($locale.'.'.$field)<label class="error">{{ $message }}</label>@enderror
                        </div>
                    @endforeach
                    <div class="form-group">
                        <label for="meta_description-{{ $locale }}">{{ trans('catalog::catalog_category.labels.meta_description') }}</label>
                        <textarea id="meta_description-{{ $locale }}" name="{{ $locale }}[meta_description]" maxlength="500" rows="2" class="form-control">{{ old($locale.'.meta_description', $category?->translate($locale)?->meta_description) }}</textarea>
                        @error($locale.'.meta_description')<label class="error">{{ $message }}</label>@enderror
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    jQuery(function() {
        jQuery('#main_form').validate({
            ignore: [],
            errorPlacement: function(error, element) {
                error.insertAfter(element);
            },
            invalidHandler: function(event, validator) {
                if (validator.errorList.length) {
                    var pane = jQuery(validator.errorList[0].element).closest('.tab-pane');
                    jQuery('a[href="#' + pane.attr('id') + '"]').tab('show');
                }
            },
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });

        @if (! $category)
        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });
        @endif
    });
</script>
@endpush
