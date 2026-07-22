<?php

namespace Modules\Customer\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Role\Models\Role;
use Modules\Customer\Emails\ResetPasswordMail;

class Customer extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable;
    
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
        'is_new',
        'status'
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

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordMail($token));
    }

    //Please Don't remove below Line
	//AppendFunctionHere

    public function address()
    {
         return $this->hasMany('Modules\Customer\Models\CustomerAddress', 'customer_id');
    }
}