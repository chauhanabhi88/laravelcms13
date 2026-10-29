<?php

declare(strict_types=1);

namespace Modules\Finance\DTO;

/**
 * The winning commission or fee rule for a seller, category and date.
 */
final readonly class ResolvedRule
{
    public function __construct(
        public int $ruleId,
        public string $scope,
        public string $type,
        public string $value,
        public ?string $appliesTo = null,
        public ?string $chargedTo = null,
    ) {}

    public function isPercent(): bool
    {
        return $this->type === 'percent';
    }

    public function isZero(): bool
    {
        return (float) $this->value === 0.0;
    }
}
