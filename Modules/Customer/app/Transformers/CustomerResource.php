<?php

declare(strict_types=1);

namespace Modules\Customer\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Models\Customer;

/**
 * The signed-in customer's own profile for the mobile API (M08 §5.8).
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->displayName(),
            'phone' => $this->phone,
            'phone_verified' => $this->phone_verified_at !== null,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'preferred_locale' => $this->preferred_locale,
            'marketing_opt_in' => (int) $this->marketing_opt_in === (int) config('core.yes'),
            'is_reseller' => $this->isReseller(),
            'reseller_business_name' => $this->reseller_business_name,
            'has_password' => $this->hasPassword(),
            'needs_profile' => $this->needsProfileCompletion(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
