<?php

namespace Modules\Mail\Http\Controllers;
use Modules\Mail\Models\MailLog;

class Mailer extends \Illuminate\Mail\Mailer
{
    public function sendSwiftMessage($message)
    {
        try {
            return $this->swift->send($message, $this->failedRecipients);
        } catch(\Exception $e) {
            $mailLog = new MailLog();
            $mailLog->createMailLog($message, config('mail.mail_log_status.failed'), $e->getMessage());
            $this->forceReconnection();
            throw($e);
        } finally {
           $this->forceReconnection();
        }
    }

}


