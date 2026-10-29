<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Customer\Models\Customer;

/**
 * A customer's own referral share code (created on first visit of the referral page).
 *
 * @property int $id
 * @property int $customer_id
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReferralCode extends MarketplaceModel
{
    protected $table = 'referral_code';

    protected $fillable = ['customer_id', 'code'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['customer_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
