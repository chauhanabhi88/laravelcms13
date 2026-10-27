<?php

declare(strict_types=1);

namespace Modules\Seller\Models;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\StrictLoading;
use Modules\Seller\Database\Factories\SellerUserFactory;
use Modules\Seller\Notifications\SellerResetPassword;

/**
 * A login of a seller business: the provider model of the "seller" guard.
 *
 * Deliberately has no hasPermission() method: Core's Gate::before() hands every ability
 * to a user that has one, which would bypass the seller policies.
 *
 * @property int $id
 * @property int $seller_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property Carbon|null $phone_verified_at
 * @property string $password
 * @property string $role
 * @property int $status
 * @property Carbon|null $last_login_at
 * @property-read Seller $seller
 */
class SellerUser extends Authenticatable implements CanResetPassword, StrictLoading
{
    /** @use HasFactory<SellerUserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';

    public const ROLE_CATALOG = 'catalog';

    public const ROLE_ORDERS = 'orders';

    public const ROLE_FINANCE = 'finance';

    protected $table = 'seller_user';

    protected $fillable = [
        'seller_id',
        'name',
        'email',
        'phone',
        'phone_verified_at',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'status' => 'integer',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    /**
     * Whether the user may open a panel area: owners open everything, staff their own role's area.
     */
    public function canAccessArea(string ...$areas): bool
    {
        return $this->isOwner() || in_array($this->role, $areas, true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SellerResetPassword($token));
    }

    protected static function newFactory(): SellerUserFactory
    {
        return SellerUserFactory::new();
    }
}
