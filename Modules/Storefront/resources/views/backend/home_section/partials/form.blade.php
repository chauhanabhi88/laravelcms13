{{-- Home section form shared by create and edit. $homeSection is null on create. --}}
@php
    $section = $homeSection ?? null;
    $type = old('type', $section?->type ?? 'hero');
    $config = (array) ($section?->config ?? []);
    $isEnabled = (int) old('status', $section?->status ?? config('core.enabled')) === (int) config('core.enabled');
    $dateFormat = (string) config('core.encrypt.php_datepicker_format', 'd-m-Y');
    $selectedCategories = array_map('intval', (array) old('category_ids', $config['category_ids'] ?? []));
    $typeFields = [
        'hero' => 'banner', 'banner_row' => 'banner', 'category_tiles' => 'categories',
        'collection' => 'collection', 'html_block' => 'block', 'blog_posts' => 'limit',
        'trust_strip' => 'trust', 'products_for_you' => 'listing', 'flash_deal' => 'limit',
    ];
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('storefront::home_section.titles.general') }}</strong></div>
    <div class="card-body">
        <div class="form-group">
            <label for="type">{{ trans('storefront::home_section.labels.type') }}</label><span class="asterisk"> *</span>
            <select id="type" name="type" class="custom-select required {{ $errors->has('type') ? 'is-invalid' : '' }}">
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}" data-fields="{{ $typeFields[$value] ?? '' }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<label class="error">{{ $message }}</label>@enderror
        </div>

        @foreach ($languageOptions as $locale => $language)
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="title-{{ $locale }}">{{ trans('storefront::home_section.labels.title') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label>
                    <input type="text" id="title-{{ $locale }}" name="{{ $locale }}[title]" maxlength="150" class="form-control {{ $errors->has($locale.'.title') ? 'is-invalid' : '' }}"
                        value="{{ old($locale.'.title', $section?->translate($locale)?->title) }}">
                    @error($locale.'.title')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="col-md-6 form-group">
                    <label for="subtitle-{{ $locale }}">{{ trans('storefront::home_section.labels.subtitle') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label>
                    <input type="text" id="subtitle-{{ $locale }}" name="{{ $locale }}[subtitle]" maxlength="255" class="form-control"
                        value="{{ old($locale.'.subtitle', $section?->translate($locale)?->subtitle) }}">
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('storefront::home_section.titles.content') }}</strong></div>
    <div class="card-body">
        <div class="form-group" data-section-field="banner">
            <label for="banner_group_code">{{ trans('storefront::home_section.labels.banner_group_code') }}</label><span class="asterisk"> *</span>
            <select id="banner_group_code" name="banner_group_code" class="custom-select {{ $errors->has('banner_group_code') ? 'is-invalid' : '' }}">
                @foreach ($bannerGroupOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('banner_group_code', $config['banner_group_code'] ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('banner_group_code')<label class="error">{{ $message }}</label>@enderror
        </div>

        <div class="form-group" data-section-field="categories">
            <label for="category_ids">{{ trans('storefront::home_section.labels.category_ids') }}</label>
            <select id="category_ids" name="category_ids[]" multiple size="10" class="form-control {{ $errors->has('category_ids') ? 'is-invalid' : '' }}">
                @foreach ($categoryOptions as $value => $label)
                    <option value="{{ $value }}" @selected(in_array((int) $value, $selectedCategories, true))>{{ $label }}</option>
                @endforeach
            </select>
            <small class="form-text text-muted">{{ trans('storefront::home_section.labels.category_ids_help') }}</small>
            @error('category_ids')<label class="error">{{ $message }}</label>@enderror
        </div>

        <div class="form-group" data-section-field="collection">
            <label for="collection_id">{{ trans('storefront::home_section.labels.collection_id') }}</label><span class="asterisk"> *</span>
            <select id="collection_id" name="collection_id" class="custom-select {{ $errors->has('collection_id') ? 'is-invalid' : '' }}">
                @foreach ($collectionOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('collection_id', $config['collection_id'] ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('collection_id')<label class="error">{{ $message }}</label>@enderror
        </div>

        <div class="form-group" data-section-field="block">
            <label for="block_code">{{ trans('storefront::home_section.labels.block_code') }}</label><span class="asterisk"> *</span>
            <select id="block_code" name="block_code" class="custom-select {{ $errors->has('block_code') ? 'is-invalid' : '' }}">
                @foreach ($blockOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('block_code', $config['block_code'] ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('block_code')<label class="error">{{ $message }}</label>@enderror
        </div>

        <div class="form-group" data-section-field="collection limit">
            <label for="limit">{{ trans('storefront::home_section.labels.limit') }}</label>
            <input type="number" id="limit" name="limit" min="1" max="40" class="form-control {{ $errors->has('limit') ? 'is-invalid' : '' }}" value="{{ old('limit', $config['limit'] ?? '') }}">
            @error('limit')<label class="error">{{ $message }}</label>@enderror
        </div>

        <p class="text-muted mb-0" data-section-field="listing">{{ trans('storefront::home_section.labels.listing_help') }}</p>
        <p class="text-muted mb-0" data-section-field="trust">{{ trans('storefront::home_section.labels.trust_strip_help') }}</p>
    </div>
</div>

<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('storefront::home_section.titles.schedule') }}</strong></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 form-group">
                <label for="sort_order">{{ trans('storefront::home_section.labels.sort_order') }}</label>
                <input type="number" id="sort_order" name="sort_order" min="0" class="form-control {{ $errors->has('sort_order') ? 'is-invalid' : '' }}" value="{{ old('sort_order', $section?->sort_order ?? $nextSortOrder) }}">
                @error('sort_order')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="col-md-3 form-group">
                <label for="starts_at">{{ trans('storefront::home_section.labels.starts_at') }}</label>
                <input type="text" id="starts_at" name="starts_at" autocomplete="off" placeholder="{{ strtolower(str_replace(['d', 'm', 'Y'], ['dd', 'mm', 'yyyy'], $dateFormat)) }}" class="form-control datepicker-field {{ $errors->has('starts_at') ? 'is-invalid' : '' }}"
                    value="{{ old('starts_at', $section?->starts_at?->format($dateFormat)) }}">
                @error('starts_at')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="col-md-3 form-group">
                <label for="ends_at">{{ trans('storefront::home_section.labels.ends_at') }}</label>
                <input type="text" id="ends_at" name="ends_at" autocomplete="off" placeholder="{{ strtolower(str_replace(['d', 'm', 'Y'], ['dd', 'mm', 'yyyy'], $dateFormat)) }}" class="form-control datepicker-field {{ $errors->has('ends_at') ? 'is-invalid' : '' }}"
                    value="{{ old('ends_at', $section?->ends_at?->format($dateFormat)) }}">
                @error('ends_at')<label class="error">{{ $message }}</label>@enderror
            </div>
            <div class="col-md-3">
                <label>{{ trans('storefront::home_section.labels.status') }}</label>
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
        function toggleSectionFields() {
            var fields = (jQuery('#type option:selected').data('fields') || '').toString();
            jQuery('[data-section-field]').each(function() {
                var wanted = jQuery(this).data('section-field').toString().split(' ');
                jQuery(this).toggle(wanted.indexOf(fields) !== -1);
            });
        }

        jQuery('#type').on('change', toggleSectionFields);
        toggleSectionFields();

        if (jQuery.fn.datepicker) {
            jQuery('.datepicker-field').datepicker({ dateFormat: "{{ config('core.encrypt.datepicker_format') }}" });
        }

        jQuery('#main_form').validate({
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });
    });
</script>
@endpush
