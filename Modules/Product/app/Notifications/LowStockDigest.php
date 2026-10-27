<?php

declare(strict_types=1);

namespace Modules\Product\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily email listing a seller's variants that ran low on stock since the last digest
 * (FR-SCT-007). Sent by `php artisan product:low-stock-digest`.
 */
class LowStockDigest extends Notification
{
    use Queueable;

    /**
     * @param  list<array{name: string, sku: string, available: int, threshold: int}>  $variants
     */
    public function __construct(public readonly array $variants) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(trans('product::seller.mail.low_stock.subject', ['count' => count($this->variants)]))
            ->line(trans('product::seller.mail.low_stock.intro', ['count' => count($this->variants)]));

        foreach (array_slice($this->variants, 0, 50) as $variant) {
            $message->line(trans('product::seller.mail.low_stock.line', $variant));
        }

        if (count($this->variants) > 50) {
            $message->line(trans('product::seller.mail.low_stock.more', ['count' => count($this->variants) - 50]));
        }

        return $message->action(trans('product::seller.mail.low_stock.action'), frontRoute('seller.inventory.index', [['stock' => 'low']]));
    }
}
