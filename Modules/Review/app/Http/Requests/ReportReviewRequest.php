<?php

declare(strict_types=1);

namespace Modules\Review\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /reviews/{id}/report: a reason from config('review.report_reasons') and an optional note.
 */
class ReportReviewRequest extends FormRequest
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
        return [
            'reason' => ['required', Rule::in((array) config('review.report_reasons', []))],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => trans('review::front.report.reason'),
            'comment' => trans('review::front.report.comment'),
        ];
    }
}
