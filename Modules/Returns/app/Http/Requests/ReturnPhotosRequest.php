<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Returns\Support\ReturnSettings;

/**
 * Wizard step 2: new photos (jpg / png / webp, 5 MB each) and draft photos to drop. The count
 * limit and "required for this reason" are checked against the draft by the controller.
 */
class ReturnPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.ReturnSettings::maxImages()],
            'photos.*' => ['file', 'image', 'mimes:'.implode(',', (array) config('returns.uploads.mimes', ['jpg', 'jpeg', 'png', 'webp'])), 'max:'.(int) config('returns.uploads.max_size_kb', 5120)],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string', 'regex:/^[A-Za-z0-9]{40}\.jpg$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photos' => trans('returns::front.wizard.photos'),
            'photos.*' => trans('returns::front.wizard.photo'),
        ];
    }
}
