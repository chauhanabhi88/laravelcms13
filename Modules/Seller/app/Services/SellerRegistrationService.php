<?php

declare(strict_types=1);

namespace Modules\Seller\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\SequenceService;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

/**
 * Creates a seller business in "onboarding" with its owner login once the mobile OTP is verified.
 */
class SellerRegistrationService
{
    public function __construct(private readonly SequenceService $sequences) {}

    /**
     * @param  array{name: string, email: string, phone: string, password_hash: string}  $data  phone in E.164
     */
    public function register(array $data): SellerUser
    {
        return DB::transaction(function () use ($data): SellerUser {
            $seller = Seller::query()->create([
                'code' => $this->sequences->next('seller', 'S'),
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'status' => SellerStatus::Onboarding,
                'onboarding_step' => 1,
            ]);

            $user = new SellerUser([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'phone_verified_at' => Carbon::now(),
                'role' => SellerUser::ROLE_OWNER,
                'status' => config('core.enabled'),
            ]);

            // Already hashed when the registration was parked in the session before the OTP step.
            $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => $data['password_hash']]));
            $user->seller()->associate($seller);
            $user->save();

            return $user;
        });
    }
}
