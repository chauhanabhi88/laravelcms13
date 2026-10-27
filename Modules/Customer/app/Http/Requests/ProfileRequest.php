<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Customer\Models\Customer;
use Modules\Language\Repositories\LanguageRepository;

/**
 * Storefront profile (FR-CUS-005): name, email, gender, date of birth, language, marketing
 * consent and picture. The mobile number is the sign-in identity and is not edited here;
 * email is required only for accounts without one. contact_number is deprecated but still accepted.
 */
class ProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $customer = new Customer;
        $maxUpload = $this->getMaxUpload();
        $maxUpload *= 1024;
        $imageTypes = ! empty(settings('customer', 'image_type')) ? settings('customer', 'image_type') : 'jpeg,jpg,png';
        $minUploadSize = 1024 * (int) settings('customer', 'min_upload_size');
        $current = auth('customer')->user();

        return [
            'first_name' => 'required|max:255',
            'last_name' => 'nullable|max:255',
            'email' => [
                Rule::requiredIf(fn () => blank($current?->phone)),
                'nullable',
                'email',
                'max:191',
                'unique:'.$customer->getTable().',email,'.(int) auth('customer')->id().',id,deleted_at,NULL',
            ],
            'profile_picture' => 'nullable|image|mimes:'.$imageTypes.'|max:'.$maxUpload.($minUploadSize ? '|min:'.$minUploadSize : ''),
            'contact_number' => 'nullable|numeric',
            'gender' => ['nullable', Rule::in(Customer::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'preferred_locale' => ['nullable', Rule::in($this->locales())],
            'marketing_opt_in' => ['nullable', Rule::in([config('core.yes'), config('core.no')])],
        ];
    }

    /**
     * Storefront languages (Language module), or just the app locale when none are set up.
     *
     * @return array<int, string>
     */
    public function locales(): array
    {
        $locales = array_keys((array) app(LanguageRepository::class)->getLanguageOptions());

        return $locales !== [] ? array_map('strval', $locales) : [(string) config('app.locale')];
    }

    private function getMaxUpload()
    {
        $maxUploadSize = settings('customer', 'max_upload_size');
        $maxUploadServer = (int) (ini_get('upload_max_filesize')) > (int) (ini_get('post_max_size')) ? (int) (ini_get('post_max_size')) : (int) (ini_get('upload_max_filesize'));

        $maxUpload = $maxUploadSize ? $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize : $maxUploadServer;

        return $maxUpload;
    }

    /**
     * Determine if the customer is authorized to make this request.
     *
     * @return bool
     */
    public function messages()
    {
        return [
            '*.required' => trans('customer::customer.messages.required'),
            'email.email' => trans('customer::customer.messages.invalid_email'),
            'email.unique' => trans('customer::customer.messages.email_unique'),
        ];
    }
}
