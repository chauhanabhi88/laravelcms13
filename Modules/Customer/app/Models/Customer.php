<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Modules\Customer\Database\Factories\CustomerFactory;
use Modules\Customer\Emails\ResetPasswordMail;

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
    ];

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

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
