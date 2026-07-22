<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class UserToken extends Model
{
    protected $table = 'user_tokens';
    protected $fillable = ['user_id', '_token'];

    //Please Don't remove below Line
	//AppendFunctionHere
}
