<?php

namespace Modules\Theme\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{

	protected $table = 'themes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'setting'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];

    //Please Don't remove below Line
    //AppendFunctionHere

    public function getPermissions()
    {
        $permissions = $this->role->permissions;
        if($permissions) {
            return json_decode($permissions, true);
        }
        return [];
    }

    public function hasPermission($permission)
    {
        $permissions = $this->getPermissions();
        if(array_key_exists($permission, $permissions)) {
            return true;
        }
        return false;
    }

}

