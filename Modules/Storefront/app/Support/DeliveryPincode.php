<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

/**
 * The buyer's delivery pincode: remembered in the session after a delivery check, else the
 * signed-in customer's default address. Prefills the product page's pincode checker.
 */
class DeliveryPincode
{
    public const SESSION_KEY = 'delivery_pincode';

    public const PATTERN = '/^[1-9][0-9]{5}$/';

    public function current(Request $request): ?string
    {
        $remembered = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($remembered) && self::isValid($remembered)) {
            return $remembered;
        }

        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            return null;
        }

        $postalCode = CustomerAddress::query()
            ->where('customer_id', $customer->getKey())
            ->where('is_default_address', 1)
            ->orderByDesc('id')
            ->value('postal_code');

        $postalCode = is_string($postalCode) ? trim($postalCode) : null;

        return $postalCode !== null && self::isValid($postalCode) ? $postalCode : null;
    }

    public function remember(Request $request, string $pincode): void
    {
        if ($request->hasSession() && self::isValid($pincode)) {
            $request->session()->put(self::SESSION_KEY, $pincode);
        }
    }

    public static function isValid(string $pincode): bool
    {
        return preg_match(self::PATTERN, $pincode) === 1;
    }
}
