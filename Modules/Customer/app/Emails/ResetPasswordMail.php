<?php

namespace Modules\Customer\Emails;

use Modules\Mail\Models\MailTemplate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordMail extends ResetPassword
{
    public function toMail($notifiable)
    {
        $body = "";
        $token = $this->token;
        $mailRepository = new MailTemplate;
        $mailTemplate = $mailRepository->where('slug', "customer-reset-password")->first();
        if(isset($mailTemplate) && !empty($mailTemplate)) {
            $body = $mailTemplate->setMailParams([
                'email'=> $notifiable->email,
                'token' => $token,
                'reset_url' => route('customer.reset', updateUrlParams(['type' => config('core.route_type'), ['token' => $token, 'email' => $notifiable->email]]))
            ])->getContent();
            $cc = []; $bcc = [];
            if(isset($mailTemplate->cc) && !empty($mailTemplate->cc)) {
                $cc = explode(',', trim($mailTemplate->cc, ","));
            }

            if(isset($mailTemplate->bcc) && !empty($mailTemplate->bcc)) {
                $bcc = explode(',', trim($mailTemplate->bcc, ","));
            }

            return (new MailMessage)
            ->cc($cc)
            ->bcc($bcc)
            ->view('mail::body', compact('body', 'token'));

        }
        return (new MailMessage)
            ->view('mail::body', compact('body'));
    }
}
