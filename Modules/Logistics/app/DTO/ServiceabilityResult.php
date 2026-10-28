<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

use Modules\Logistics\Models\CourierRule;

/**
 * The carriers able to take a package (empty = not serviceable) and the aggregator's own
 * recommendation. choose() applies a rule strategy (plan M13 §5.2).
 */
final readonly class ServiceabilityResult
{
    /**
     * @param  list<CarrierQuote>  $carriers
     */
    public function __construct(
        public array $carriers,
        public ?string $recommendedId = null,
    ) {}

    public static function none(): self
    {
        return new self([]);
    }

    public function isServiceable(): bool
    {
        return $this->carriers !== [];
    }

    /**
     * Only the carriers that collect cash, when COD is needed.
     */
    public function withCod(bool $cod): self
    {
        if (! $cod) {
            return $this;
        }

        return new self(array_values(array_filter($this->carriers, fn (CarrierQuote $quote): bool => $quote->cod)), $this->recommendedId);
    }

    public function find(string $id): ?CarrierQuote
    {
        foreach ($this->carriers as $quote) {
            if ($quote->id === $id) {
                return $quote;
            }
        }

        return null;
    }

    public function cheapest(): ?CarrierQuote
    {
        $best = null;

        foreach ($this->carriers as $quote) {
            if ($best === null || $quote->rate->compare($best->rate) < 0) {
                $best = $quote;
            }
        }

        return $best;
    }

    /**
     * Fewest transit days; carriers without an estimate come last, ties go to the cheaper one.
     */
    public function fastest(): ?CarrierQuote
    {
        $best = null;

        foreach ($this->carriers as $quote) {
            if ($best === null) {
                $best = $quote;

                continue;
            }

            $days = $quote->etaDays ?? PHP_INT_MAX;
            $bestDays = $best->etaDays ?? PHP_INT_MAX;

            if ($days < $bestDays || ($days === $bestDays && $quote->rate->compare($best->rate) < 0)) {
                $best = $quote;
            }
        }

        return $best;
    }

    /**
     * The aggregator's recommendation, else the cheapest.
     */
    public function recommended(): ?CarrierQuote
    {
        return ($this->recommendedId !== null ? $this->find($this->recommendedId) : null) ?? $this->cheapest();
    }

    /**
     * The first available carrier of $preferredIds; falls back to the recommendation.
     *
     * @param  list<string>  $preferredIds
     */
    public function firstOf(array $preferredIds): ?CarrierQuote
    {
        foreach ($preferredIds as $id) {
            if (($quote = $this->find((string) $id)) !== null) {
                return $quote;
            }
        }

        return $this->recommended();
    }

    /**
     * @param  list<string>  $preferredIds
     */
    public function choose(string $strategy, array $preferredIds = []): ?CarrierQuote
    {
        return match ($strategy) {
            CourierRule::STRATEGY_CHEAPEST => $this->cheapest(),
            CourierRule::STRATEGY_FASTEST => $this->fastest(),
            CourierRule::STRATEGY_PREFERRED_LIST => $this->firstOf($preferredIds),
            default => $this->recommended(),
        };
    }

    /**
     * @return array{carriers: list<array<string, mixed>>, recommended_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'carriers' => array_map(fn (CarrierQuote $quote): array => $quote->toArray(), $this->carriers),
            'recommended_id' => $this->recommendedId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            array_values(array_map(fn (array $quote): CarrierQuote => CarrierQuote::fromArray($quote), (array) ($data['carriers'] ?? []))),
            isset($data['recommended_id']) ? (string) $data['recommended_id'] : null,
        );
    }
}
