<?php

declare(strict_types=1);

namespace Modules\Storefront\DTO;

use Illuminate\Support\Carbon;

/**
 * Result of a delivery check: serviceability, COD and the estimated delivery window.
 */
final readonly class DeliveryEstimate
{
    public function __construct(
        public string $pincode,
        public bool $serviceable,
        public bool $codAvailable,
        public ?Carbon $eddFrom,
        public ?Carbon $eddTo,
        public string $message,
        public ?string $returnText = null,
    ) {}

    public static function unserviceable(string $pincode, string $message): self
    {
        return new self($pincode, false, false, null, null, $message);
    }

    /**
     * A serviceable estimate whose message is "Estimated delivery by Thu, 30 Oct".
     */
    public static function serviceable(string $pincode, bool $codAvailable, Carbon $eddFrom, Carbon $eddTo, ?string $returnText = null): self
    {
        $message = trans('storefront::product.delivery.delivery_by', ['date' => self::formatWindow($eddFrom, $eddTo)]);

        return new self($pincode, true, $codAvailable, $eddFrom, $eddTo, $message, $returnText);
    }

    /**
     * "Thu, 30 Oct" or "Thu, 30 Oct – Sat, 1 Nov" (null when not serviceable).
     */
    public function eddText(): ?string
    {
        if ($this->eddFrom === null || $this->eddTo === null) {
            return null;
        }

        return self::formatWindow($this->eddFrom, $this->eddTo);
    }

    protected static function formatWindow(Carbon $from, Carbon $to): string
    {
        $format = (string) config('storefront.delivery.date_format', 'D, j M');

        return $from->isSameDay($to)
            ? $to->translatedFormat($format)
            : $from->translatedFormat($format).' – '.$to->translatedFormat($format);
    }

    /**
     * @return array{pincode: string, serviceable: bool, cod: bool, edd_from: string|null, edd_to: string|null, edd_text: string|null, message: string, delivery_text: string|null, cod_text: string|null, return_text: string|null}
     */
    public function toArray(): array
    {
        $eddText = $this->eddText();

        return [
            'pincode' => $this->pincode,
            'serviceable' => $this->serviceable,
            'cod' => $this->codAvailable,
            'edd_from' => $this->eddFrom?->toDateString(),
            'edd_to' => $this->eddTo?->toDateString(),
            'edd_text' => $eddText,
            'message' => $this->message,
            'delivery_text' => $eddText !== null ? trans('storefront::product.delivery.delivery_by', ['date' => $eddText]) : null,
            'cod_text' => $this->serviceable
                ? trans($this->codAvailable ? 'storefront::product.delivery.cod_available' : 'storefront::product.delivery.cod_unavailable')
                : null,
            'return_text' => $this->serviceable ? $this->returnText : null,
        ];
    }
}
