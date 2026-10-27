<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Support\CurrentSeller;
use Modules\Seller\Support\Phone;

/**
 * Settings › Staff: a staff login with one role. Owners are created only at registration.
 */
class StaffRequest extends FormRequest
{
    /**
     * Editing another seller's login (or an owner) answers 404 before any validation runs.
     */
    public function authorize(): bool
    {
        if ($this->route('staff') !== null) {
            $staff = SellerUser::query()->findOrFail((int) $this->route('staff'));
            Gate::forUser(app(CurrentSeller::class)->user())->authorize('manage', $staff);
            abort_if($staff->isOwner(), 404);
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone_e164' => Phone::normalise($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staffId = $this->route('staff') !== null ? (int) $this->route('staff') : null;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('seller_user', 'email')->ignore($staffId)],
            'phone' => ['required', 'string', 'max:20'],
            'phone_e164' => ['required', 'string', Rule::unique('seller_user', 'phone')->ignore($staffId)],
            'role' => ['required', Rule::in([SellerUser::ROLE_CATALOG, SellerUser::ROLE_ORDERS, SellerUser::ROLE_FINANCE])],
            'status' => ['required', Rule::in([(int) config('core.enabled'), (int) config('core.disabled')])],
            'password' => [$staffId ? 'nullable' : 'required', 'confirmed', RegisterRequest::passwordRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_e164.required' => trans('seller::validation.phone.invalid'),
            'phone_e164.unique' => trans('seller::validation.phone.taken'),
            'email.unique' => trans('seller::validation.email.taken'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('seller::onboarding.attributes');
    }
}
