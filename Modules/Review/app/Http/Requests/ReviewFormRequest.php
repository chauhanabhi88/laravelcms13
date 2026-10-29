<?php

declare(strict_types=1);

namespace Modules\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Modules\Review\DTO\ReviewSubmission;
use Modules\Review\Support\ReviewSettings;

/**
 * The write / edit review form: stars (required), optional title and text (min / max length),
 * up to max_images photos, and on edit the ids of photos to remove. ReviewService checks the
 * photo total and eligibility again.
 */
class ReviewFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Shared with the API request.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:'.(int) config('review.title_max_length', 120)],
            'body' => ['nullable', 'string', 'min:'.ReviewSettings::minBodyLength(), 'max:'.(int) config('review.body_max_length', 2000)],
            'photos' => ['nullable', 'array', 'max:'.ReviewSettings::maxImages()],
            'photos.*' => ['file', 'image', 'mimes:'.implode(',', (array) config('review.uploads.mimes', ['jpg', 'jpeg', 'png', 'webp'])), 'max:'.(int) config('review.uploads.max_size_kb', 5120)],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => trans('review::front.form.rating'),
            'title' => trans('review::front.form.title'),
            'body' => trans('review::front.form.body'),
            'photos' => trans('review::front.form.photos'),
            'photos.*' => trans('review::front.form.photo'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => trans('review::front.errors.rating_required'),
        ];
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

    /**
     * @return list<int>
     */
    public function removeImageIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('remove_images', [])));
    }
}
