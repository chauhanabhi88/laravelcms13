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
 * The seller's QC of a returned package (POST seller.return.qc): passed, or failed with a note
 * and optionally a claim (reason, description, 1–5 photos). Another seller's return answers 404
 * before validation runs.
 */
class ReturnQcRequest extends FormRequest
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
            'result' => ['required', Rule::in(['passed', 'failed'])],
            'note' => ['nullable', 'string', 'max:500', 'required_if:result,failed'],
            'raise_claim' => ['nullable', 'in:1'],
            'claim_reason' => ['nullable', 'required_if:raise_claim,1', Rule::in((array) config('returns.claim_reasons', []))],
            'claim_description' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.ReturnSettings::maxImages(), 'required_if:raise_claim,1'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('returns.uploads.max_size_kb', 5120)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'result' => trans('returns::seller.qc.result'),
            'note' => trans('returns::seller.qc.note'),
            'claim_reason' => trans('returns::seller.claim.reason'),
            'photos' => trans('returns::seller.claim.photos'),
        ];
    }

    public function returnRequest(): ReturnRequest
    {
        return $this->resolvedReturn ??= ReturnRequest::query()->where('return_no', (string) $this->route('returnNo'))->firstOrFail();
    }

    public function passed(): bool
    {
        return $this->validated('result') === 'passed';
    }

    /**
     * @return array{reason: string, description: string|null, photos: list<UploadedFile>}|null
     */
    public function claim(): ?array
    {
        if ($this->passed() || $this->validated('raise_claim') !== '1') {
            return null;
        }

        return [
            'reason' => (string) $this->validated('claim_reason'),
            'description' => $this->validated('claim_description'),
            'photos' => array_values(array_filter((array) $this->file('photos', []), fn ($file): bool => $file instanceof UploadedFile)),
        ];
    }
}
