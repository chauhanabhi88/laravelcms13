<?php

namespace Modules\User\Models;

use Modules\Role\Models\Role;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Emails\CustomResetPasswordMail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable;

    protected $table = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'role_id',
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


    //Please Don't remove below Line
    //AppendFunctionHere


    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @inheritdoc
     */
    public function hasRoleSlug($slug)
    {
        return $this->role()->whereSlug($slug)->count() >= 1;
    }

    public function getPermissions()
    {
        if (empty($this->role)) {
            Auth::guard()->logout();
            redirect()->route(config("user.redirect_route_not_logged_in"), updateUrlParams());
        }
        if(isset($this->role->permissions) && !empty($this->role->permissions)) {
            $permissions = $this->role->permissions;
            if ($permissions) {
                return json_decode($permissions, true);
            }
        }
        return [];
    }

    public function hasPermission($permission)
    {
        $permissions = $this->getPermissions();
        if (in_array($permission, $permissions)) {
            return true;
        }
        return false;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordMail($token));
    }
}
