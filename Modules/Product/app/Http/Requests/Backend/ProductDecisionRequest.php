<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Block / unblock a product. Whether a reason is required is the service's rule.
 * Authorisation is the route's can: middleware.
 */
class ProductDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }
}
