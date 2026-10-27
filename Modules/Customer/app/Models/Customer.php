<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Modules\Customer\Database\Factories\CustomerFactory;
use Modules\Customer\Emails\ResetPasswordMail;
use Modules\Customer\Support\PhoneNumber;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'customer';

    protected $guard = 'customer';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'profile_picture',
        'email',
        'password',
        'email_verified_at',
        'contact_number',
        'status',
        'gender',
        'date_of_birth',
        'preferred_locale',
        'marketing_opt_in',
    ];

    /**
     * Gender options for the profile form (FR-CUS-005); values are stored, labels come from lang.
     */
    public const GENDERS = ['female', 'male', 'other'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'deletion_requested_at' => 'datetime',
        'is_reseller' => 'integer',
        'marketing_opt_in' => 'integer',
        'cod_blocked' => 'integer',
        'rto_count' => 'integer',
    ];

    /**
     * Whether new sign-ups must verify their email before logging in.
     *
     * The setting is saved under the "customer" settings group; older code
     * read it from "core", which is kept as a fallback. Unset means "required"
     * (the original behaviour).
     */
    public static function requiresEmailVerification(): bool
    {
        $value = settings('customer', 'email_verification') ?? settings('core', 'email_verification');

        return (int) $value !== (int) config('customer.email_verification.no');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordMail($token));
    }

    // Please Don't remove below Line
    // AppendFunctionHere

    public function address()
    {
        return $this->hasMany('Modules\Customer\Models\CustomerAddress', 'customer_id');
    }

    /**
     * @return HasMany<CustomerWishlist, $this>
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(CustomerWishlist::class, 'customer_id');
    }

    /**
     * @return HasMany<CustomerDataRequest, $this>
     */
    public function dataRequests(): HasMany
    {
        return $this->hasMany(CustomerDataRequest::class, 'customer_id');
    }

    public function isReseller(): bool
    {
        return (int) $this->is_reseller === (int) config('core.yes');
    }

    public function isCodBlocked(): bool
    {
        return (int) $this->cod_blocked === (int) config('core.yes');
    }

    public function isEnabled(): bool
    {
        return (int) $this->status === (int) config('customer.enabled_status');
    }

    /**
     * Accounts created by OTP have no password until the customer sets one.
     */
    public function hasPassword(): bool
    {
        return filled($this->getAuthPassword());
    }

    /**
     * New OTP accounts start without a name; they get a one-time "complete your profile" prompt.
     */
    public function needsProfileCompletion(): bool
    {
        return blank($this->first_name);
    }

    /**
     * Name for greetings and lists: full name, else email, else the masked phone.
     */
    public function displayName(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);

        if ($name !== '') {
            return $name;
        }

        return (string) ($this->email ?: PhoneNumber::display($this->phone));
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
