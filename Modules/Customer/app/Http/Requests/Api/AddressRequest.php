<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Requests\Api;

use Modules\Customer\Http\Requests\StoreAddressRequest;

/**
 * Same rules as the storefront form, with flat field names and the Passport "api" guard.
 */
class AddressRequest extends StoreAddressRequest
{
    protected string $prefix = '';

    protected function guardName(): string
    {
        return 'api';
    }
}
