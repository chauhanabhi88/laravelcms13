<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Support\ReturnSettings;
use Modules\Seller\Support\CurrentSeller;

/**
 * A seller's claim on a received return (POST seller.return_claim.store): reason, description
 * and 1–5 photos. Another seller's return answers 404 before validation runs.
 */
class ReturnClaimRequest extends FormRequest
{
    protected ?ReturnRequest $resolvedReturn = null;

    public function authorize(): bool
    {
        Gate::forUser(app(CurrentSeller::class)->user())->authorize('manage', $this->returnRequest());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in((array) config('returns.claim_reasons', []))],
            'description' => ['required', 'string', 'max:1000'],
            'photos' => ['required', 'array', 'min:1', 'max:'.ReturnSettings::maxImages()],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('returns.uploads.max_size_kb', 5120)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => trans('returns::seller.claim.reason'),
            'description' => trans('returns::seller.claim.description'),
            'photos' => trans('returns::seller.claim.photos'),
        ];
    }

    public function returnRequest(): ReturnRequest
    {
        return $this->resolvedReturn ??= ReturnRequest::query()->where('return_no', (string) $this->route('returnNo'))->firstOrFail();
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), fn ($file): bool => $file instanceof UploadedFile));
    }
}
