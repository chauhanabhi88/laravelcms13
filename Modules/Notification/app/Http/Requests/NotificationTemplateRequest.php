<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Support\EventCatalogue;

/**
 * Admin template form: one template per event, channel and audience. SMS bodies must match
 * the DLT-registered text, so keep provider_template_id in sync with the body.
 */
class NotificationTemplateRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'event_key' => ['required', 'string', Rule::in(app(EventCatalogue::class)->keys())],
            'channel' => ['required', 'string', Rule::in(NotificationTemplate::CHANNELS)],
            'audience' => [
                'required',
                'string',
                Rule::in((array) config('notification.audiences')),
                Rule::unique('notification_template', 'audience')
                    ->where('event_key', (string) $this->input('event_key'))
                    ->where('channel', (string) $this->input('channel'))
                    ->ignore($id ? (int) $id : null),
            ],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'provider_template_id' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::in([config('core.enabled'), config('core.disabled')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audience.unique' => trans('notification::notification_template.messages.duplicate'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_key' => trans('notification::notification_template.labels.event_key'),
            'channel' => trans('notification::notification_template.labels.channel'),
            'audience' => trans('notification::notification_template.labels.audience'),
            'title' => trans('notification::notification_template.labels.title'),
            'body' => trans('notification::notification_template.labels.body'),
            'provider_template_id' => trans('notification::notification_template.labels.provider_template_id'),
        ];
    }
}
