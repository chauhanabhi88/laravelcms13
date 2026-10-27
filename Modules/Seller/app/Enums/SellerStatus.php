<?php

declare(strict_types=1);

namespace Modules\Seller\Enums;

/**
 * Seller lifecycle (SRS §7.5). Transitions are declared on Modules\Seller\Models\Seller.
 */
enum SellerStatus: string
{
    case Onboarding = 'onboarding';
    case PendingVerification = 'pending_verification';
    case ChangesRequested = 'changes_requested';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return trans('seller::seller.statuses.'.$this->value);
    }

    /**
     * Badge colour on admin (bootstrap) screens.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::PendingVerification => 'info',
            self::ChangesRequested, self::Onboarding => 'warning',
            self::Rejected, self::Suspended => 'danger',
            self::Deactivated => 'secondary',
        };
    }

    /**
     * Chip variant in the supplier panel (theme components).
     */
    public function chip(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::PendingVerification => 'accent',
            self::ChangesRequested, self::Onboarding => 'deal',
            self::Rejected, self::Suspended => 'danger',
            self::Deactivated => 'neutral',
        };
    }

    /**
     * The seller can still edit the onboarding wizard.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Onboarding, self::ChangesRequested], true);
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
