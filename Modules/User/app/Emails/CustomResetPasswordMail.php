<?php

namespace Modules\User\Emails;

use Modules\Mail\Models\MailTemplate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPasswordMail extends ResetPassword
{
    public function toMail($notifiable)
    {
        $body = '';
        $token = $this->token;
        $mailRepository = new MailTemplate;
        $mailTemplate = $mailRepository->where('slug', "admin-reset-password")->first();
        if(isset($mailTemplate) && !empty($mailTemplate)) {
            $body = $mailTemplate->setMailParams([
                'email'=> $notifiable->email,
                'token' => $token,
                'reset_url' => route('password.reset', updateUrlParams([['token' => $token, 'email' => $notifiable->email]]))
            ])->getContent();
            return (new MailMessage)->view('mail::body', compact('body', 'token'));
        }
        return (new MailMessage)->view('mail::body', compact('body', 'token'));
    }
}
