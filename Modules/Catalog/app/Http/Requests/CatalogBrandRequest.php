<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Support\CatalogSettings;

class CatalogBrandRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:160', 'regex:'.CatalogCategoryRequest::SLUG_PATTERN, Rule::unique('catalog_brand', 'slug')->ignore($this->route('id'))],
            'logo' => ['nullable', 'image', 'mimes:'.config('catalog.upload.image_types'), 'max:'.config('catalog.upload.max_size_kb')],
            'size_chart_id' => ['nullable', 'integer', Rule::exists('catalog_size_chart', 'id')],
            'is_verified' => ['nullable', Rule::in([config('core.yes'), config('core.no')])],
            'status' => ['nullable', Rule::in([config('core.enabled'), config('core.disabled')])],
        ];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $rules["{$locale}.name"] = ['required', 'string', 'max:120'];
            $rules["{$locale}.description"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
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
        $attributes = [];
        foreach (CatalogSettings::contentLocales() as $locale) {
            $attributes["{$locale}.name"] = trans('catalog::catalog_brand.labels.name')." ({$locale})";
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'is_verified' => $this->filled('is_verified') ? config('core.yes') : config('core.no'),
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
        ]);
    }
}
