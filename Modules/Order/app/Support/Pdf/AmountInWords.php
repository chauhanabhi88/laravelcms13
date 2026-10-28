<?php

declare(strict_types=1);

namespace Modules\Order\Support\Pdf;

use Modules\Core\Support\Money;

/**
 * "Total in words" of a tax invoice (GST Rule 46) in the Indian numbering system:
 * ₹1,23,456.50 → "Rupees One Lakh Twenty Three Thousand Four Hundred Fifty Six and Fifty Paise Only".
 */
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /**
     * Indian place values, largest first: [divisor, name].
     */
    private const SCALES = [[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']];

    public static function rupees(Money $amount): string
    {
        $paise = abs($amount->paise);
        $rupees = intdiv($paise, 100);
        $rest = $paise % 100;

        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::words($rupees));

        if ($rest > 0) {
            $words .= ' and '.self::words($rest).' Paise';
        }

        return $words.' Only';
    }

    private static function words(int $number): string
    {
        $parts = [];

        foreach (self::SCALES as [$divisor, $name]) {
            if ($number >= $divisor) {
                $parts[] = self::words(intdiv($number, $divisor)).' '.$name;
                $number %= $divisor;
            }
        }

        if ($number > 0) {
            $parts[] = $number < 20
                ? self::ONES[$number]
                : trim(self::TENS[intdiv($number, 10)].' '.self::ONES[$number % 10]);
        }

        return implode(' ', $parts);
    }
}
