<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

use Modules\Core\Support\Money;

/**
 * One carrier able to take a package: its aggregator id, freight rate, COD charge and
 * estimated transit days.
 */
final readonly class CarrierQuote
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $rate,
        public Money $codCharge,
        public ?int $etaDays,
        public bool $cod,
    ) {}

    /**
     * @return array{id: string, name: string, rate: string, cod_charge: string, eta_days: int|null, cod: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate' => $this->rate->toDecimal(),
            'cod_charge' => $this->codCharge->toDecimal(),
            'eta_days' => $this->etaDays,
            'cod' => $this->cod,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['name'],
            Money::of((string) ($data['rate'] ?? '0')),
            Money::of((string) ($data['cod_charge'] ?? '0')),
            isset($data['eta_days']) ? (int) $data['eta_days'] : null,
            (bool) ($data['cod'] ?? false),
        );
    }
}
