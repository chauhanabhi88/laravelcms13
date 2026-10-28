<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Customer\Models\Customer;
use Modules\Notification\Services\DeviceTokenRegistry;
use Modules\Seller\Models\SellerUser;
use Modules\User\Models\User;

/**
 * Who a notifiable is and how to reach it: customers (phone, email, app tokens), seller
 * users (phone, email) and admin users (email). Disabled accounts receive nothing.
 */
class RecipientResolver
{
    public function __construct(private readonly DeviceTokenRegistry $tokens) {}

    public function audience(object $notifiable): ?string
    {
        return match (true) {
            $notifiable instanceof Customer => 'customer',
            $notifiable instanceof SellerUser => 'seller',
            $notifiable instanceof User => 'admin',
            default => null,
        };
    }

    public function isReachable(object $notifiable): bool
    {
        return match (true) {
            $notifiable instanceof Customer => $notifiable->isEnabled() && ! $notifiable->trashed(),
            $notifiable instanceof SellerUser => $notifiable->isEnabled(),
            $notifiable instanceof User => (int) $notifiable->status === (int) config('core.enabled') && ! $notifiable->trashed(),
            default => false,
        };
    }

    public function name(object $notifiable): string
    {
        return match (true) {
            $notifiable instanceof Customer => $notifiable->displayName(),
            $notifiable instanceof SellerUser, $notifiable instanceof User => (string) ($notifiable->name ?: $notifiable->email),
            default => '',
        };
    }

    public function email(object $notifiable): ?string
    {
        $email = $notifiable instanceof Model ? trim((string) $notifiable->getAttribute('email')) : '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Verified mobile number in E.164 (customers and seller users only).
     */
    public function phone(object $notifiable): ?string
    {
        if (! $notifiable instanceof Customer && ! $notifiable instanceof SellerUser) {
            return null;
        }

        $phone = trim((string) $notifiable->phone);

        return preg_match('/^\+\d{8,15}$/', $phone) ? $phone : null;
    }

    /**
     * @return list<string>
     */
    public function deviceTokens(object $notifiable): array
    {
        return $notifiable instanceof Customer ? $this->tokens->tokensFor($notifiable) : [];
    }

    /**
     * Whether the notifiable has an address for a channel; null when it has, else the reason.
     */
    public function missingRoute(object $notifiable, string $channel): ?string
    {
        $reachable = match ($channel) {
            'mail' => $this->email($notifiable) !== null,
            'sms', 'whatsapp' => $this->phone($notifiable) !== null,
            'push' => $this->deviceTokens($notifiable) !== [],
            'database' => $notifiable instanceof Model && method_exists($notifiable, 'notifications'),
            default => false,
        };

        return $reachable ? null : 'no_route';
    }

    /**
     * Masked destination for the log.
     */
    public function destination(object $notifiable, string $channel): string
    {
        return match ($channel) {
            'mail' => Mask::email($this->email($notifiable)),
            'sms', 'whatsapp' => Mask::phone($this->phone($notifiable)),
            'push' => implode(', ', array_map(Mask::token(...), array_slice($this->deviceTokens($notifiable), 0, 3))),
            default => 'in-app',
        };
    }
}
