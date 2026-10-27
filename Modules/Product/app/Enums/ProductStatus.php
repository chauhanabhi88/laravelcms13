<?php

declare(strict_types=1);

namespace Modules\Product\Enums;

/**
 * Product lifecycle (SRS §7.2). Transitions are declared on Modules\Product\Models\Product.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case PendingQc = 'pending_qc';
    case Live = 'live';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Blocked = 'blocked';

    public function label(): string
    {
        return trans('product::product.statuses.'.$this->value);
    }

    /**
     * Badge colour on admin (bootstrap) screens.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Live => 'success',
            self::PendingQc => 'info',
            self::Draft => 'secondary',
            self::Paused => 'warning',
            self::Rejected, self::Blocked => 'danger',
        };
    }

    /**
     * Chip variant in the supplier panel (theme components).
     */
    public function chip(): string
    {
        return match ($this) {
            self::Live => 'success',
            self::PendingQc => 'accent',
            self::Draft => 'neutral',
            self::Paused => 'deal',
            self::Rejected, self::Blocked => 'danger',
        };
    }

    /**
     * Has been approved at least once and is still the seller's to show or pause: content
     * edits go to pending changes instead of overwriting what buyers see.
     */
    public function isPublished(): bool
    {
        return $this === self::Live || $this === self::Paused;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
