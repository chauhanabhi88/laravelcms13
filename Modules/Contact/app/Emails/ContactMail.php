<?php

namespace Modules\Contact\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Mail\Models\MailTemplate;
use Modules\Contact\Models\Contact;

class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The Contact instance.
     *
     * @var Contact
     */
    protected $contactEntity;    

    public function __construct(Contact $contactEntity)
    {
        $this->contactEntity = $contactEntity;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
         try{
                $body = '';
                $receiverMail = settings("mail","recipient_admin_email");
                
                $receiverUserName = config('mail.username');
                $contact = $this->contactEntity;
                $mailRepository = new MailTemplate;
                $mailTemplate = $mailRepository->where(['slug'=>'contact-us-inquiry','status'=>config("core.enabled")])->first(); 
                if(isset($mailTemplate) && !empty($mailTemplate)){
                   $body = $mailTemplate->setMailParams([
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'question' => $contact->content,
                    ])->getContent();
                    $body = '<html>'.html_entity_decode($body).'</html>';
                    return $mailTemplate->sendMail($this, $receiverMail,  $body);
                }  
            return $this->view('mail::body', compact('body'));
        }catch(\Throwable $e){
            return $e->getMessage();
        }
    }
}
