<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerResetPassword extends Model
{
    protected $table = 'customer_password_resets';

    protected $fillable = [
        'email',
        'token',
        'created_at'
    ];
}