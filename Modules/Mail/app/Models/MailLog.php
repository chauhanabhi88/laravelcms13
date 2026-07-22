<?php

namespace Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Mail\Repositories\MailLogRepository;

class MailLog extends Model
{

    protected $table = 'mail_log';
    protected $fillable = ['from_email', 'from_name', 'to_email', 'to_name', 'cc', 'bcc', 'subject', 'body', 'status', 'exception'];


    public function createMailLog($message = null, $status = null, $exception, $isEvent = false)
    {
        if (config('mail.enable_mail_log')) {
            if ($isEvent) {
                $event = $message;
                $message = $event->message;
            }
            if (!empty($message)) {

                $fromEmailAddress = null;
                $fromName = null;
                $toEmail = null;
                $toName = null;

                $from = !empty($message->getHeaders()->get('From')) ? $message->getHeaders()->getHeaderBody('From')[0]->toString() : null;
                if (!empty($from)) {
                    $fromEmailAddress = trim($this->getEmailString($from, "<", ">"));
                    $fromName = trim(substr($from, 0, strpos($from, '<')));
                }
                $to = !empty($message->getHeaders()->get('To')) ? $message->getHeaders()->getHeaderBody('To')[0]->toString() : null;
                if (!empty($to)) {
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
                    'body' => (isset($event->data['body']) && !empty($event->data['body']) ? $event->data['body'] : $message->getBody()->getBody()),
                ];

                if (isset($exception) && !empty($exception)) {
                    $data['exception'] = $exception;
                }
                if (isset($status) && !empty($status)) {
                    $data['status'] = $status;
                }

                $mailLogRepo = app(MailLogRepository::class);
                $mailLogRepo->create($data);
            }
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
