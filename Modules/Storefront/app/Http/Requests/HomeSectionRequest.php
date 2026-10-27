<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Support\CatalogSettings;

/**
 * Home section form. The type decides which config field is required; schedule dates arrive
 * in the admin datepicker format (core.encrypt.php_datepicker_format).
 */
class HomeSectionRequest extends FormRequest
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
        $dateFormat = (string) config('core.encrypt.php_datepicker_format', 'd-m-Y');

        $rules = [
            'type' => ['required', 'string', Rule::in((array) config('storefront.home_section_types'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['nullable', Rule::in([config('core.enabled'), config('core.disabled')])],
            'starts_at' => ['nullable', 'date_format:'.$dateFormat],
            'ends_at' => ['nullable', 'date_format:'.$dateFormat, 'after_or_equal:starts_at'],
            'banner_group_code' => ['nullable', 'required_if:type,hero,banner_row', 'string', Rule::exists('banner_group', 'code')],
            'category_ids' => ['nullable', 'array', 'max:24'],
            'category_ids.*' => ['integer', Rule::exists('catalog_category', 'id')],
            'collection_id' => ['nullable', 'required_if:type,collection', 'integer', Rule::exists('product_collection', 'id')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:40'],
            'block_code' => ['nullable', 'required_if:type,html_block', 'string', Rule::exists('cms_block', 'slug')->whereNull('deleted_at')],
        ];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $rules["{$locale}.title"] = ['nullable', 'string', 'max:150'];
            $rules["{$locale}.subtitle"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'banner_group_code' => trans('storefront::home_section.labels.banner_group_code'),
            'collection_id' => trans('storefront::home_section.labels.collection_id'),
            'block_code' => trans('storefront::home_section.labels.block_code'),
        ];

        foreach (CatalogSettings::contentLocales() as $locale) {
            $attributes["{$locale}.title"] = trans('storefront::home_section.labels.title')." ({$locale})";
        }

        return $attributes;
    }

    /**
     * The section's config for its type.
     *
     * @return array<string, mixed>
     */
    public function sectionConfig(): array
    {
        $limit = $this->filled('limit') ? (int) $this->input('limit') : null;

        return match ($this->input('type')) {
            'hero', 'banner_row' => ['banner_group_code' => (string) $this->input('banner_group_code')],
            'category_tiles' => ['category_ids' => array_values(array_unique(array_map('intval', (array) $this->input('category_ids', []))))],
            'collection' => ['collection_id' => (int) $this->input('collection_id'), 'limit' => $limit ?? (int) config('storefront.home_defaults.collection_limit', 12)],
            'html_block' => ['block_code' => (string) $this->input('block_code')],
            'blog_posts' => ['limit' => $limit ?? (int) config('storefront.home_defaults.blog_posts_limit', 3)],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
            'category_ids' => array_values(array_filter((array) $this->input('category_ids', []), fn ($id): bool => $id !== null && $id !== '')),
        ]);
    }
}
