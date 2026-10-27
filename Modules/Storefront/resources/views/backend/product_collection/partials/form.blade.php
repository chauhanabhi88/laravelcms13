{{-- Collection form shared by create and edit. $productCollection is null on create. --}}
@php
    $collection = $productCollection ?? null;
    $rules = (array) old('rules', $collection?->rules ?? []);
    $type = old('type', $collection?->type ?? \Modules\Storefront\Models\ProductCollection::TYPE_RULE);
    $isEnabled = (int) old('status', $collection?->status ?? config('core.enabled')) === (int) config('core.enabled');
    $defaultLocale = app()->getLocale();
    $selected = fn (string $key): array => array_map('intval', (array) ($rules[$key] ?? []));
@endphp
<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('storefront::product_collection.titles.general') }}</strong></div>
    <div class="card-body">
        @foreach ($languageOptions as $locale => $language)
            <div class="form-group">
                <label for="name-{{ $locale }}">{{ trans('storefront::product_collection.labels.name') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label><span class="asterisk"> *</span>
                <input type="text" id="name-{{ $locale }}" name="{{ $locale }}[name]" maxlength="150"
                    class="form-control required {{ $errors->has($locale.'.name') ? 'is-invalid' : '' }}"
                    value="{{ old($locale.'.name', $collection?->translate($locale)?->name) }}"
                    @if ($locale === $defaultLocale) data-slug="source" @endif>
                @error($locale.'.name')<label class="error">{{ $message }}</label>@enderror
            </div>
        @endforeach

        {{ normalText('slug', 'storefront::product_collection.labels.slug', $errors, old('slug', $collection?->slug), ['class' => 'form-control required', 'data-slug' => 'target', 'maxlength' => 160]) }}

        @foreach ($languageOptions as $locale => $language)
            <div class="form-group">
                <label for="description-{{ $locale }}">{{ trans('storefront::product_collection.labels.description') }} @if (count($languageOptions) > 1)({{ $language }})@endif</label>
                <textarea id="description-{{ $locale }}" name="{{ $locale }}[description]" rows="2" maxlength="2000" class="form-control">{{ old($locale.'.description', $collection?->translate($locale)?->description) }}</textarea>
            </div>
        @endforeach

        <div class="row">
            <div class="col-md-4 form-group">
                <label for="type">{{ trans('storefront::product_collection.labels.type') }}</label>
                <select id="type" name="type" class="custom-select">
                    @foreach (['rule', 'manual'] as $value)
                        <option value="{{ $value }}" @selected($type === $value)>{{ trans('storefront::product_collection.types.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label for="sort">{{ trans('storefront::product_collection.labels.sort') }}</label>
                <select id="sort" name="sort" class="custom-select">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('sort', $collection?->sort ?? 'popularity') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label>{{ trans('storefront::product_collection.labels.status') }}</label>
                <div>
                    <label class="switch">
                        <input type="checkbox" value="{{ config('core.enabled') }}" name="status" {{ $isEnabled ? 'checked' : '' }}>
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="banner_image">{{ trans('storefront::product_collection.labels.banner_image') }}</label>
            @if ($collection?->banner_image)
                <div class="mb-2">
                    <img src="{{ $collection->bannerUrl() }}" alt="" style="max-height: 80px" class="img-thumbnail">
                    <label class="ml-2"><input type="checkbox" name="remove_banner_image" value="1"> {{ trans('storefront::product_collection.labels.remove_banner_image') }}</label>
                </div>
            @endif
            <input type="file" id="banner_image" name="banner_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
            @error('banner_image')<label class="error">{{ $message }}</label>@enderror
        </div>
    </div>
</div>

<div class="card card-info card-outline">
    <div class="card-header"><strong>{{ trans('storefront::product_collection.titles.rules') }}</strong></div>
    <div class="card-body">
        <div data-collection-type="rule">
            <p class="text-muted">{{ trans('storefront::product_collection.labels.rules_help') }}</p>
            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="rules-max-price">{{ trans('storefront::product_collection.labels.max_price') }}</label>
                    <input type="number" id="rules-max-price" name="rules[max_price]" min="0" step="1" class="form-control {{ $errors->has('rules.max_price') ? 'is-invalid' : '' }}" value="{{ $rules['max_price'] ?? '' }}">
                    @error('rules.max_price')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="col-md-4 form-group">
                    <label for="rules-min-discount">{{ trans('storefront::product_collection.labels.min_discount') }}</label>
                    <input type="number" id="rules-min-discount" name="rules[min_discount]" min="0" max="95" class="form-control {{ $errors->has('rules.min_discount') ? 'is-invalid' : '' }}" value="{{ $rules['min_discount'] ?? '' }}">
                    @error('rules.min_discount')<label class="error">{{ $message }}</label>@enderror
                </div>
                <div class="col-md-4 form-group">
                    <label for="rules-min-rating">{{ trans('storefront::product_collection.labels.min_rating') }}</label>
                    <input type="number" id="rules-min-rating" name="rules[min_rating]" min="0" max="5" step="0.1" class="form-control {{ $errors->has('rules.min_rating') ? 'is-invalid' : '' }}" value="{{ $rules['min_rating'] ?? '' }}">
                    @error('rules.min_rating')<label class="error">{{ $message }}</label>@enderror
                </div>
            </div>
            @foreach (['category_ids' => $categoryOptions, 'brand_ids' => $brandOptions, 'seller_ids' => $sellerOptions] as $key => $options)
                <div class="form-group">
                    <label for="rules-{{ $key }}">{{ trans('storefront::product_collection.labels.'.$key) }}</label>
                    <select id="rules-{{ $key }}" name="rules[{{ $key }}][]" multiple size="6" class="form-control {{ $errors->has('rules.'.$key) ? 'is-invalid' : '' }}">
                        @foreach ($options as $value => $label)
                            <option value="{{ $value }}" @selected(in_array((int) $value, $selected($key), true))>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('rules.'.$key)<label class="error">{{ $message }}</label>@enderror
                </div>
            @endforeach
        </div>

        <div class="form-group" data-collection-type="manual">
            <label for="product_ids">{{ trans('storefront::product_collection.labels.product_ids') }}</label><span class="asterisk"> *</span>
            <textarea id="product_ids" name="product_ids" rows="3" class="form-control {{ $errors->has('product_ids') ? 'is-invalid' : '' }}">{{ old('product_ids', implode(', ', $collection?->manualProductIds() ?? [])) }}</textarea>
            <small class="form-text text-muted">{{ trans('storefront::product_collection.labels.product_ids_help') }}</small>
            @error('product_ids')<label class="error">{{ $message }}</label>@enderror
        </div>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    jQuery(function() {
        function toggleCollectionType() {
            var type = jQuery('#type').val();
            jQuery('[data-collection-type]').each(function() {
                jQuery(this).toggle(jQuery(this).data('collection-type') === type);
            });
        }

        jQuery('#type').on('change', toggleCollectionType);
        toggleCollectionType();

        jQuery('#main_form').validate({
            submitHandler: function(form) {
                if (!beenSubmitted) {
                    beenSubmitted = true;
                    loaderShow();
                    form.submit();
                }
            }
        });

        @if (! $collection)
        jQuery('[data-slug="source"]').each(function() {
            jQuery(this).slug();
        });
        @endif
    });
</script>
@endpush
