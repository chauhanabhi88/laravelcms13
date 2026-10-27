<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Attribute\Models\Attribute;

/**
 * The category's own attribute mapping: mappings[<attribute id>][flags, sort_order, allowed_option_ids].
 */
class CategoryAttributeRequest extends FormRequest
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
        $flag = ['nullable', Rule::in([config('core.yes'), config('core.no')])];

        return [
            'mappings' => ['nullable', 'array'],
            'mappings.*' => ['array'],
            'mappings.*.is_variant_axis' => $flag,
            'mappings.*.is_filterable' => $flag,
            'mappings.*.is_required' => $flag,
            'mappings.*.show_on_card' => $flag,
            'mappings.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'mappings.*.allowed_option_ids' => ['nullable', 'array'],
            'mappings.*.allowed_option_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ids = array_map('intval', array_keys((array) $this->input('mappings', [])));

                if ($ids !== [] && Attribute::query()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                    $validator->errors()->add('mappings', trans('validation.exists', ['attribute' => trans('catalog::catalog_category.attributes.attribute')]));
                }
            },
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mappings(): array
    {
        return (array) $this->validated('mappings', []);
    }
}
