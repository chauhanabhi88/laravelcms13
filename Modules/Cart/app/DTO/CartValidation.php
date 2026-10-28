<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

/**
 * What CartValidator found per cart item (keyed by cart_item id).
 */
final readonly class CartValidation
{
    /**
     * @param  array<int, list<LineIssue>>  $issues
     */
    public function __construct(public array $issues = []) {}

    /**
     * @return list<LineIssue>
     */
    public function for(int $itemId): array
    {
        return $this->issues[$itemId] ?? [];
    }

    public function hasBlockingIssues(): bool
    {
        foreach ($this->issues as $issues) {
            foreach ($issues as $issue) {
                if ($issue->blocking) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isClean(): bool
    {
        return $this->issues === [];
    }
}
