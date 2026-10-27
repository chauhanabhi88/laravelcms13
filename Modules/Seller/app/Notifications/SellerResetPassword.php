<?php

declare(strict_types=1);

namespace Modules\Seller\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset link for a supplier panel login (seller.password.reset).
 */
class SellerResetPassword extends ResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return frontRoute('seller.password.reset', [$this->token, ['email' => $notifiable->getEmailForPasswordReset()]]);
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.sellers.expire', 60);

        return (new MailMessage)
            ->subject(trans('seller::auth.reset_mail.subject', ['app' => config('app.name')]))
            ->line(trans('seller::auth.reset_mail.intro'))
            ->action(trans('seller::auth.reset_mail.action'), $this->resetUrl($notifiable))
            ->line(trans('seller::auth.reset_mail.expiry', ['minutes' => $minutes]))
            ->line(trans('seller::auth.reset_mail.ignore'));
    }
}
