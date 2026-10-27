<?php

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogCommentRequest extends FormRequest
{
    /**
     * The route is already behind auth:customer.
     */
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
            'subject' => ['required', 'string', 'max:150'],
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject' => trans('blog::blog_front.labels.subject'),
            'comment' => trans('blog::blog_front.labels.comment'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => is_string($this->subject) ? trim(strip_tags($this->subject)) : $this->subject,
            'comment' => is_string($this->comment) ? trim(strip_tags($this->comment)) : $this->comment,
        ]);
    }
}
