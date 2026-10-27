<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Catalog\Http\Requests\CatalogCategoryRequest;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Storefront\Models\ProductCollection;

/**
 * Collection form: rules for a "rule" collection, a comma separated product id list for a
 * "manual" one (kept in that order).
 */
class ProductCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'slug' => ['required', 'string', 'max:160', 'regex:'.CatalogCategoryRequest::SLUG_PATTERN, Rule::unique('product_collection', 'slug')->ignore($this->route('id'))],
            'type' => ['required', Rule::in([ProductCollection::TYPE_RULE, ProductCollection::TYPE_MANUAL])],
            'sort' => ['required', Rule::in(array_keys(ProductCollection::SORTS))],
            'status' => ['nullable', Rule::in([config('core.enabled'), config('core.disabled')])],
            'banner_image' => ['nullable', 'image', 'mimes:'.config('storefront.upload.image_types'), 'max:'.config('storefront.upload.max_size_kb')],
            'rules' => ['nullable', 'array'],
            'rules.category_ids' => ['nullable', 'array', 'max:50'],
            'rules.category_ids.*' => ['integer', Rule::exists('catalog_category', 'id')],
            'rules.brand_ids' => ['nullable', 'array', 'max:50'],
            'rules.brand_ids.*' => ['integer', Rule::exists('catalog_brand', 'id')],
            'rules.seller_ids' => ['nullable', 'array', 'max:50'],
            'rules.seller_ids.*' => ['integer', Rule::exists('seller', 'id')],
            'rules.max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'rules.min_discount' => ['nullable', 'integer', 'min:0', 'max:95'],
            'rules.min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'product_ids' => ['nullable', 'required_if:type,'.ProductCollection::TYPE_MANUAL, 'string', 'max:5000'],
        ];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $rules["{$locale}.name"] = ['required', 'string', 'max:150'];
            $rules["{$locale}.description"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') !== ProductCollection::TYPE_MANUAL || $validator->errors()->has('product_ids')) {
                    return;
                }

                $ids = $this->productIds();
                $known = $ids === [] ? [] : DB::table('product')->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $unknown = array_diff($ids, $known);

                if ($ids === [] || $unknown !== []) {
                    $validator->errors()->add('product_ids', trans('storefront::product_collection.messages.unknown_products', ['ids' => implode(', ', $unknown)]));
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,]+/', (string) $this->input('product_ids'), -1, PREG_SPLIT_NO_EMPTY) ?: []))));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function collectionRules(): ?array
    {
        if ($this->input('type') === ProductCollection::TYPE_MANUAL) {
            return null;
        }

        $rules = (array) $this->input('rules', []);
        $clean = [];

        foreach (['category_ids', 'brand_ids', 'seller_ids'] as $key) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($rules[$key] ?? [])))));
            if ($ids !== []) {
                $clean[$key] = $ids;
            }
        }

        foreach (['max_price', 'min_discount', 'min_rating'] as $key) {
            if (is_numeric($rules[$key] ?? null)) {
                $clean[$key] = $rules[$key] + 0;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => trans('catalog::catalog_category.messages.slug_format'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['product_ids' => trans('storefront::product_collection.labels.product_ids')];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $attributes["{$locale}.name"] = trans('storefront::product_collection.labels.name')." ({$locale})";
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
        ]);
    }
}
