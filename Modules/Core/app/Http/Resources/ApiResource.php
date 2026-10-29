<?php

declare(strict_types=1);

namespace Modules\Core\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\Money;

/**
 * Base of the API resources (plan M20 §3.5). The envelope's "data" key is the only wrapper, so the
 * resource never adds its own: the ApiEnvelope middleware turns a resource response into
 * {success, code, message, data}.
 *
 * Helpers keep the field conventions in one place: money is a decimal string with two places,
 * timestamps are ISO-8601 with the UTC offset.
 */
abstract class ApiResource extends JsonResource
{
    public static $wrap = null;

    /**
     * A money amount: {"amount": "249.00", "formatted": "₹249"}.
     *
     * @return array{amount: string, formatted: string}
     */
    protected function money(Money $money): array
    {
        return ['amount' => $money->toDecimal(), 'formatted' => $money->format()];
    }

    protected function iso(?DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }
}
