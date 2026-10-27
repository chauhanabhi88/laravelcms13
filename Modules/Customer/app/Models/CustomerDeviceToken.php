<?php

declare(strict_types=1);

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Push token of a customer's app install (table from the 2025_09_25 migration, which
 * referenced this model before it existed). Written by the OTP API when a device_token is sent.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string|null $device_token
 * @property string|null $device_type
 */
class CustomerDeviceToken extends Model
{
    protected $table = 'customer_device_token';

    protected $fillable = ['customer_id', 'device_token', 'device_type'];
}
