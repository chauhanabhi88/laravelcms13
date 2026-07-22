<?php

namespace Modules\Role\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\User\Models\User;

class Role extends Model
{
    protected $table = 'role';
    protected $fillable = ['name', 'slug', 'permissions'];

    //Please Don't remove below Line
	//AppendFunctionHere
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
