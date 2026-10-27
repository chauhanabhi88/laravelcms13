<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Support\CatalogSettings;

/**
 * Create and update a category. Commerce defaults are optional: blank means "inherit".
 */
class CatalogCategoryRequest extends FormRequest
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $imageRule = ['nullable', 'image', 'mimes:'.config('catalog.upload.image_types'), 'max:'.config('catalog.upload.max_size_kb')];

        $rules = [
            'slug' => ['required', 'string', 'max:160', 'regex:'.self::SLUG_PATTERN, Rule::unique('catalog_category', 'slug')->ignore($this->route('id'))],
            'parent_id' => ['nullable', 'integer', Rule::exists('catalog_category', 'id')->whereNull('deleted_at')],
            'image' => $imageRule,
            'banner_image' => $imageRule,
            'icon' => ['nullable', 'string', 'max:255'],
            'gst_percent' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'hsn_code' => ['nullable', 'regex:/^[0-9]{4}([0-9]{2}){0,2}$/'],
            'return_window_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'return_type' => ['nullable', Rule::in((array) config('catalog.return_types'))],
            'dispatch_sla_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'size_chart_id' => ['nullable', 'integer', Rule::exists('catalog_size_chart', 'id')],
            'min_selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'show_in_menu' => ['nullable', Rule::in([config('core.yes'), config('core.no')])],
            'status' => ['nullable', Rule::in([config('core.enabled'), config('core.disabled')])],
        ];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $rules["{$locale}.name"] = ['required', 'string', 'max:120'];
            $rules["{$locale}.description"] = ['nullable', 'string', 'max:5000'];
            $rules["{$locale}.meta_title"] = ['nullable', 'string', 'max:255'];
            $rules["{$locale}.meta_description"] = ['nullable', 'string', 'max:500'];
            $rules["{$locale}.meta_keywords"] = ['nullable', 'string', 'max:255'];
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
            $attributes["{$locale}.name"] = trans('catalog::catalog_category.labels.name')." ({$locale})";
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'show_in_menu' => $this->filled('show_in_menu') ? config('core.yes') : config('core.no'),
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
        ]);
    }
}
