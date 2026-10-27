<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Backend;

use Illuminate\Validation\Rule;

/**
 * Bulk approve / reject from the QC queue grid: {ids[], action, reason, reason_codes[]}.
 */
class ProductQcBulkRequest extends ProductQcDecisionRequest
{
    public const MAX_IDS = 100;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'min:1'],
            'action' => ['required', Rule::in(['approve', 'reject'])],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->validated('ids'))));
    }
}
