<?php

declare(strict_types=1);

namespace Modules\Review\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Http\Requests\ReviewFormRequest;

/**
 * POST /api/v1/reviews (multipart): the order item, stars, optional title / text and photos[].
 * Errors use the API envelope.
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['order_item_id' => ['required', 'integer']] + array_diff_key(ReviewFormRequest::fieldRules(), array_flip(['remove_images', 'remove_images.*']));
    }

    public function submission(): ReviewSubmission
    {
        return new ReviewSubmission(
            (int) $this->validated('rating'),
            $this->validated('title'),
            $this->validated('body'),
            array_values(array_filter((array) $this->file('photos', []), fn ($file): bool => $file instanceof UploadedFile)),
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'code' => '422',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
