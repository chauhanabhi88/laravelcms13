<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

use Illuminate\Validation\Rule;
use Modules\Product\Services\ProductFactoryService;

/**
 * Wizard step 2 (details) for a new draft (POST seller.listing.store, with
 * catalog_category_id) or an existing product (POST seller.listing.save). Shapes and lengths
 * only; the catalog rules run in ProductFactoryService, whose error keys match these fields.
 */
class StoreListingStepRequest extends SellerProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locale = app(ProductFactoryService::class)->defaultLocale();
        $maxHighlights = (int) config('product.max_highlights', 8);
        $maxHighlightLength = (int) config('product.max_highlight_length', 200);

        return [
            'catalog_category_id' => [Rule::requiredIf(! $this->hasProduct()), 'nullable', 'integer'],
            "translations.{$locale}.name" => ['required', 'string', 'max:200'],
            "translations.{$locale}.description" => ['nullable', 'string', 'max:20000'],
            "translations.{$locale}.highlights" => ['nullable', 'array', 'max:'.$maxHighlights],
            "translations.{$locale}.highlights.*" => ['nullable', 'string', 'max:'.$maxHighlightLength],
            'catalog_brand_id' => ['nullable', 'integer'],
            'brand_request' => ['nullable', 'string', 'max:120'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable'],
            'hsn_code' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
            'gst_percent' => ['nullable', Rule::in(array_map('strval', (array) config('product.gst_rates')))],
            'country_of_origin' => ['required', 'string', 'size:2', 'alpha'],
            'manufacturer_details.name' => ['nullable', 'string', 'max:200'],
            'manufacturer_details.address' => ['nullable', 'string', 'max:500'],
            'packer_details.name' => ['nullable', 'string', 'max:200'],
            'packer_details.address' => ['nullable', 'string', 'max:500'],
            'importer_details.name' => ['nullable', 'string', 'max:200'],
            'importer_details.address' => ['nullable', 'string', 'max:500'],
            'search_keywords' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $locale = app(ProductFactoryService::class)->defaultLocale();

        return [
            "translations.{$locale}.name" => trans('product::seller.wizard.details.name'),
            "translations.{$locale}.description" => trans('product::seller.wizard.details.description'),
            "translations.{$locale}.highlights" => trans('product::seller.wizard.details.highlights'),
            'country_of_origin' => trans('product::seller.wizard.details.country_of_origin'),
            'hsn_code' => trans('product::seller.wizard.details.hsn_code'),
            'gst_percent' => trans('product::seller.wizard.details.gst_percent'),
        ];
    }

    /**
     * Description is plain text: tags are stripped, line breaks and "•" / "-" bullets stay.
     */
    protected function prepareForValidation(): void
    {
        $locale = app(ProductFactoryService::class)->defaultLocale();
        $translations = (array) $this->input('translations', []);

        if (isset($translations[$locale]['description']) && is_string($translations[$locale]['description'])) {
            $translations[$locale]['description'] = trim(strip_tags($translations[$locale]['description']));
        }

        $this->merge([
            'translations' => $translations,
            'country_of_origin' => strtoupper(trim((string) $this->input('country_of_origin', 'IN'))),
        ]);
    }
}
