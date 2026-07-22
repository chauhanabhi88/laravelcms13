<?php

namespace Modules\Mail\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Modules\Mail\Repositories\MailLogRepository;

class LogSendMail
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(MessageSent $event)
    {
        if (config('mail.enable_mail_log')) {
            $message = $event->message;
            if (empty($message)) {
                return;
                exit();
            }
            
            $fromEmailAddress = null;
            $fromName = null;
            $toEmail = null;
            $toName = null;

            $from = $message->getHeaders()->getHeaderBody('From')[0]->toString();
            if ($from) {
                $fromEmailAddress = trim($this->getEmailString($from, "<", ">"));
                $fromName = trim(substr($from, 0, strpos($from, '<')));
            }
            $to = $message->getHeaders()->getHeaderBody('To')[0]->toString();
            if ($to) {
                if (strpos($to, '<')) {
                    $toEmail = trim($this->getEmailString($to, "<", ">"));
                    $toName = trim(substr($to, 0, strpos($to, '<')));
                } else {
                    $toEmail = $to;
                }
            }
            
            $data = [
                'from_email' => (!$fromEmailAddress) ? null : $fromEmailAddress,
                'from_name' => !$fromName ? null : $fromName,
                'to_email' => !$toEmail ? null : $toEmail,
                'to_name' => !$toName ? null : $toName,
                'cc' => !$message->getHeaders()->get('Cc') ? null : $message->getHeaders()->getHeaderBody('Cc')[0]->toString(),
                'bcc' => !$message->getHeaders()->get('Bcc') ? null : $message->getHeaders()->getHeaderBody('Bcc')[0]->toString(),
                'subject' => $message->getHeaders()->getHeaderBody('Subject'),
                'body' => $message->getBody()->getBody(),
            ];
            $mailLogRepo = app(MailLogRepository::class);
            $mailLogRepo->create($data);
        }
        return;
    }

    public function getEmailString($string, $start = "<", $end = ">")
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }
}
